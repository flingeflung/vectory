<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Slice 2 der Zeiterfassung/Ressourcenplanung-Idee (Ralf, 2026-09-27, siehe
 * Roadmap-Backlog): Stunden direkt im Projekt buchen. Jede Person bucht nur
 * ihre EIGENEN Stunden (wie in der klassischen Zeiterfassung), auf einen
 * Job, der sowohl am Projekt verknüpft (project_job_types, Slice 1) als
 * auch ihr selbst zugewiesen ist (person_job_types) - die Schnittmenge.
 * Schreibt in dieselbe job_hours-Tabelle wie die klassische Zeiterfassung,
 * nur mit gesetzter project_id (siehe Migrations-Kommentar).
 */
class ProjectHourController extends Controller
{
    /**
     * Reiter-Inhalt "Buchungen" - auch der Standard-Reiter beim Öffnen,
     * sobald das Projekt mindestens einen verknüpften Job hat (siehe
     * ProjectJobTypeController::form()).
     */
    public function tab(Request $request, Project $project): View
    {
        $personId = $request->user()->person_id;
        $tenantId = CurrentTenant::id();

        $bookableJobs = $this->bookableJobs($project->id, $personId, $tenantId);
        $splits = $this->splitsForPreview($project);

        $entries = DB::table('job_hours')
            ->join('job_types', 'job_types.id', '=', 'job_hours.job_type_id')
            ->where('job_hours.tenant_id', $tenantId)
            ->where('job_hours.project_id', $project->id)
            ->where('job_hours.person_id', $personId)
            ->orderByDesc('job_hours.work_date')
            ->get(['job_hours.id', 'job_hours.job_type_id', 'job_hours.work_date', 'job_hours.hours', 'job_types.code', 'job_types.name']);

        // Für den Hinweis "es gibt für diesen Tag/Job schon eine Buchung, Speichern
        // ersetzt sie" (Ralf, 2026-09-27) - bei konfigurierter Aufteilung zählt eine
        // Buchung in JEDEM Empfänger (Hauptprojekt + Unterprojekte), nicht nur hier,
        // weil genau die vom nächsten Speichern überschrieben würden.
        $relevantProjectIds = $splits ? array_column($splits, 'id') : [$project->id];
        $existingBookings = $personId
            ? DB::table('job_hours')
                ->where('tenant_id', $tenantId)
                ->where('person_id', $personId)
                ->whereIn('project_id', $relevantProjectIds)
                ->get(['job_type_id', 'work_date'])
                ->map(fn ($row) => ['job_type_id' => (int) $row->job_type_id, 'work_date' => (string) $row->work_date])
                ->unique(fn ($row) => $row['job_type_id'].'|'.$row['work_date'])
                ->values()
            : collect();

        return view('projekte.partials.project-hours-body', [
            'project' => $project,
            'bookableJobs' => $bookableJobs,
            'entries' => $entries,
            'projectHasJobs' => DB::table('project_job_types')->where('project_id', $project->id)->exists(),
            'canEditJobs' => $request->user()->can('project.edit'),
            'showAufteilungTab' => ProjectPercentageSplitController::showAufteilungTab($project),
            'splits' => $splits,
            'existingBookings' => $existingBookings,
        ]);
    }

    /**
     * Für die Live-Vorschau im Buchungsformular (Ralf, 2026-09-27: "die genaue
     * Verteilung der Zeit auf die UP anhand der %-Aufteilung, bevor ich
     * speichere") - dieselben Empfänger/Prozente, die splitAndBook() beim
     * echten Speichern verwendet, nur hier lesend fürs Frontend aufbereitet.
     * Leer (null), wenn kein Hauptprojekt oder keine Aufteilung konfiguriert -
     * dann bucht store() wie bisher direkt am Projekt selbst, keine Vorschau nötig.
     */
    private function splitsForPreview(Project $project): ?array
    {
        if ($project->verbund_rolle !== 1) {
            return null;
        }

        $rows = DB::table('project_percentage_splits')->where('hauptprojekt_id', $project->id)->orderBy('project_id')->pluck('percentage', 'project_id');
        if ($rows->isEmpty()) {
            return null;
        }

        $labels = DB::table('projects')->whereIn('id', $rows->keys())->pluck('source_pn', 'id');

        return $rows->map(fn ($percentage, $projectId) => [
            'id' => (int) $projectId,
            'label' => $labels[$projectId] ?? (string) $projectId,
            'percentage' => (float) $percentage,
        ])->values()->all();
    }

    public function store(Request $request, Project $project): \Illuminate\Http\JsonResponse
    {
        $personId = $request->user()->person_id;
        abort_unless($personId, 403);
        $tenantId = CurrentTenant::id();

        $data = $request->validate([
            'job_type_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0', 'max:24', 'decimal:0,2'],
        ]);

        $bookableIds = $this->bookableJobs($project->id, $personId, $tenantId)->pluck('id')->all();
        if (! in_array((int) $data['job_type_id'], $bookableIds, true)) {
            throw ValidationException::withMessages(['job_type_id' => __('Dieser Job ist für Sie an diesem Projekt nicht buchbar.')]);
        }

        $timeGrid = (int) DB::table('tenants')->where('id', $tenantId)->value('jobload_time_grid');
        $stepHundredths = match ($timeGrid) { 60 => 100, 30 => 50, default => 25 };
        if ((int) round($data['hours'] * 100) % $stepHundredths !== 0) {
            throw ValidationException::withMessages(['hours' => __('Stunden müssen dem Zeitraster entsprechen (:step Stunden).', [
                'step' => number_format($timeGrid / 60, 2, ',', ''),
            ])]);
        }

        // Prozentuale Aufteilung (Ralf, 2026-09-27, siehe Roadmap-Backlog): wird am
        // Hauptprojekt gebucht UND ist eine Aufteilung konfiguriert, landet die Buchung
        // NICHT als eine Zeile am Hauptprojekt, sondern wird sofort verbindlich nach den
        // JETZT gültigen Prozenten auf die Unterprojekte verteilt - spätere Änderungen der
        // Prozente wirken nur auf künftige Buchungen. Ohne konfigurierte Aufteilung: wie
        // bisher, eine Zeile am Projekt selbst.
        if ($project->verbund_rolle === 1 && (float) $data['hours'] > 0) {
            $splits = DB::table('project_percentage_splits')
                ->where('hauptprojekt_id', $project->id)
                ->orderBy('project_id')
                ->pluck('percentage', 'project_id');

            if ($splits->isNotEmpty()) {
                $notice = $this->splitAndBook($splits, $personId, $tenantId, (int) $data['job_type_id'], $data['work_date'], (int) round($data['hours'] * 100));

                return response()->json(['html' => $this->tab($request, $project)->render(), 'notice' => $notice]);
            }
        }

        $match = [
            'tenant_id' => $tenantId, 'person_id' => $personId, 'job_type_id' => $data['job_type_id'],
            'work_date' => $data['work_date'], 'project_id' => $project->id,
        ];

        if ((float) $data['hours'] <= 0) {
            DB::table('job_hours')->where($match)->delete();
        } else {
            $existing = DB::table('job_hours')->where($match)->first();
            if ($existing) {
                DB::table('job_hours')->where('id', $existing->id)->update(['hours' => $data['hours'], 'updated_at' => now()]);
            } else {
                DB::table('job_hours')->insert($match + ['hours' => $data['hours'], 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        return response()->json(['html' => $this->tab($request, $project)->render(), 'notice' => null]);
    }

    /**
     * Verteilt die eingegebenen Stunden (in Hundertsteln, wegen job_hours.hours
     * decimal(5,2)) auf die Empfänger der Prozent-Aufteilung. "Größter-Rest"-
     * Verfahren: erst abrunden, dann den verbleibenden Hundertstel-Rest den
     * Empfängern mit dem größten Rest zuschlagen - so ergibt die Summe der
     * Anteile IMMER wieder exakt die eingegebene Gesamtstundenzahl (Ralf,
     * 2026-09-27: "krumme Zahlen sind ok, Hauptsache die Summe passt").
     */
    private function splitAndBook($splits, int $personId, int $tenantId, int $jobTypeId, string $workDate, int $totalHundredths): string
    {
        $shareHundredths = [];
        $remainders = [];
        $assigned = 0;
        foreach ($splits as $projectId => $percentage) {
            $exact = $totalHundredths * ((float) $percentage) / 100;
            $floor = (int) floor($exact + 1e-9);
            $shareHundredths[$projectId] = $floor;
            $remainders[$projectId] = $exact - $floor;
            $assigned += $floor;
        }
        $leftover = $totalHundredths - $assigned;
        if ($leftover > 0) {
            arsort($remainders);
            foreach (array_slice(array_keys($remainders), 0, $leftover) as $projectId) {
                $shareHundredths[$projectId]++;
            }
        }

        $labels = DB::table('projects')->whereIn('id', array_keys($shareHundredths))->pluck('source_pn', 'id');
        $breakdown = [];

        foreach ($shareHundredths as $projectId => $hundredths) {
            $match = ['tenant_id' => $tenantId, 'person_id' => $personId, 'job_type_id' => $jobTypeId, 'work_date' => $workDate, 'project_id' => $projectId];
            if ($hundredths <= 0) {
                DB::table('job_hours')->where($match)->delete();

                continue;
            }
            $hours = round($hundredths / 100, 2);
            $existing = DB::table('job_hours')->where($match)->first();
            if ($existing) {
                DB::table('job_hours')->where('id', $existing->id)->update(['hours' => $hours, 'updated_at' => now()]);
            } else {
                DB::table('job_hours')->insert($match + ['hours' => $hours, 'created_at' => now(), 'updated_at' => now()]);
            }
            $breakdown[] = ($labels[$projectId] ?? $projectId).': '.number_format($hours, 2, ',', '.').' h';
        }

        return __('Nach der prozentualen Aufteilung auf die Unterprojekte gebucht: :breakdown', ['breakdown' => implode(', ', $breakdown)]);
    }

    public function destroy(Request $request, Project $project, int $jobHour): Response
    {
        $personId = $request->user()->person_id;
        $tenantId = CurrentTenant::id();

        // Nur die eigene Buchung an diesem Projekt - id allein würde erlauben, fremde Zeilen
        // per erratener ID zu löschen.
        DB::table('job_hours')->where('id', $jobHour)->where('tenant_id', $tenantId)
            ->where('project_id', $project->id)->where('person_id', $personId)->delete();

        return response($this->tab($request, $project)->render());
    }

    /**
     * Schnittmenge: am Projekt verknüpfte Jobs (Slice 1), die außerdem
     * dieser Person selbst zugewiesen sind (wie in der klassischen
     * Zeiterfassung) - nur die darf sie hier buchen.
     */
    private function bookableJobs(int $projectId, ?int $personId, int $tenantId)
    {
        if (! $personId) {
            return collect();
        }

        return DB::table('project_job_types')
            ->join('job_types', 'job_types.id', '=', 'project_job_types.job_type_id')
            ->join('person_job_types', function ($join) use ($personId, $tenantId) {
                $join->on('person_job_types.job_type_id', '=', 'job_types.id')
                    ->where('person_job_types.person_id', $personId)
                    ->where('person_job_types.tenant_id', $tenantId);
            })
            ->leftJoin('job_groups', function ($join) use ($tenantId) {
                $join->on('job_groups.id', '=', 'job_types.job_group_id')->where('job_groups.tenant_id', '=', $tenantId);
            })
            ->where('project_job_types.project_id', $projectId)
            ->where('job_types.tenant_id', $tenantId)
            ->where('job_types.active', true)
            ->orderBy('job_groups.sort')->orderBy('job_groups.name')->orderBy('job_types.code')->orderBy('job_types.name')
            ->get(['job_types.id', 'job_types.code', 'job_types.name', 'job_groups.name as group_name']);
    }
}
