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

        $entries = DB::table('job_hours')
            ->join('job_types', 'job_types.id', '=', 'job_hours.job_type_id')
            ->where('job_hours.tenant_id', $tenantId)
            ->where('job_hours.project_id', $project->id)
            ->where('job_hours.person_id', $personId)
            ->orderByDesc('job_hours.work_date')
            ->get(['job_hours.id', 'job_hours.job_type_id', 'job_hours.work_date', 'job_hours.hours', 'job_types.code', 'job_types.name']);

        return view('projekte.partials.project-hours-body', [
            'project' => $project,
            'bookableJobs' => $bookableJobs,
            'entries' => $entries,
            'projectHasJobs' => DB::table('project_job_types')->where('project_id', $project->id)->exists(),
            'canEditJobs' => $request->user()->can('project.edit'),
        ]);
    }

    public function store(Request $request, Project $project): Response
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

        return response($this->tab($request, $project)->render());
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
