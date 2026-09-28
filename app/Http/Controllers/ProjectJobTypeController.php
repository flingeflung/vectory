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
 * Slice 1 der Zeiterfassung/Ressourcenplanung-Idee (Ralf, 2026-09-27, siehe
 * Roadmap-Backlog): welche Jobs (job_types) für dieses Projekt relevant
 * sind - reine Zuordnung, noch KEINE Stundenbuchung (kommt als eigener,
 * separat testbarer Schritt). Gleiches Auswahl-Muster wie
 * JobloadController::saveJobs() (dort: Person wählt ihre eigenen Jobs),
 * hier: Projekt statt Person.
 */
class ProjectJobTypeController extends Controller
{
    public function __construct(
        private readonly ProjectHourController $hourController,
        private readonly ProjectPercentageSplitController $splitController,
    ) {}

    public function form(Request $request, Project $project): View
    {
        $tenantId = CurrentTenant::id();
        // Ralf, 2026-09-28: eigenes Recht statt project.edit (siehe Migration) -
        // ohne das Recht bleibt der Reiter SICHTBAR (nur lesend), kein 403 mehr.
        $canManageJobload = $request->user()->can('project.jobload.manage');

        if ($request->query('tab') === 'aufteilung') {
            return $this->splitController->tab($request, $project);
        }

        // Standard-Reiter beim Öffnen (Ralf, 2026-09-27): "Verknüpfte Jobs" nur für
        // Verwaltende, solange das Projekt noch KEINE verknüpften Jobs hat - sonst
        // (oder ohne Verwaltungsrecht, das Buchen selbst ist davon unabhängig)
        // direkt der Buchungs-Reiter, spart einen Klick. "?tab=jobs" (expliziter
        // Reiterwechsel-Klick) erzwingt den Jobs-Reiter, jetzt auch lesend erlaubt.
        $hasJobs = DB::table('project_job_types')->where('project_id', $project->id)->exists();
        if ((! $canManageJobload || $hasJobs) && $request->query('tab') !== 'jobs') {
            return $this->hourController->tab($request, $project);
        }

        $availableJobs = DB::table('job_types')
            ->where('job_types.tenant_id', $tenantId)
            ->where('job_types.active', true)
            ->leftJoin('job_groups', function ($join) use ($tenantId) {
                $join->on('job_groups.id', '=', 'job_types.job_group_id')->where('job_groups.tenant_id', '=', $tenantId);
            })
            ->orderBy('job_groups.sort')->orderBy('job_groups.name')->orderBy('job_types.code')->orderBy('job_types.name')
            ->select('job_types.id', 'job_types.code', 'job_types.name', 'job_groups.name as group_name')
            ->get();

        $selectedIds = DB::table('project_job_types')->where('project_id', $project->id)->pluck('job_type_id')->all();

        // "Vom Hauptprojekt kopieren" (Ralf, 2026-09-27): nur für Unterprojekte,
        // und nur anbietbar, wenn das Hauptprojekt selbst schon Jobs hat -
        // sonst gäbe es nichts zu kopieren.
        $hauptprojektJobIds = [];
        if ($project->verbund_rolle === 2 && $project->hauptprojekt_id) {
            $hauptprojektJobIds = DB::table('project_job_types')->where('project_id', $project->hauptprojekt_id)->pluck('job_type_id')->all();
        }

        return view('projekte.partials.project-jobs-body', [
            'project' => $project,
            'availableJobs' => $availableJobs,
            'selectedIds' => $selectedIds,
            'hauptprojektJobIds' => $hauptprojektJobIds,
            'hauptprojektTitle' => $project->hauptprojekt?->source_pn,
            'canManageJobload' => $canManageJobload,
            'showAufteilungTab' => ProjectPercentageSplitController::showAufteilungTab($project),
        ]);
    }

    public function update(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.jobload.manage'), 403);

        $tenantId = CurrentTenant::id();

        $data = $request->validate([
            'jobs' => ['array'],
            'jobs.*' => ['integer', 'distinct'],
        ]);
        $selected = array_map('intval', $data['jobs'] ?? []);

        $valid = DB::table('job_types')->where('tenant_id', $tenantId)->where('active', true)
            ->whereIn('id', $selected)->count();
        if ($valid !== count($selected)) {
            throw ValidationException::withMessages(['jobs' => __('Ungültiger Job.')]);
        }

        DB::transaction(function () use ($tenantId, $project, $selected) {
            DB::table('project_job_types')->where('project_id', $project->id)
                ->whereNotIn('job_type_id', $selected)->delete();
            foreach ($selected as $jobId) {
                DB::table('project_job_types')->updateOrInsert(
                    ['project_id' => $project->id, 'job_type_id' => $jobId],
                    ['tenant_id' => $tenantId]
                );
            }
        });

        return response()->noContent();
    }
}
