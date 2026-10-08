<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Services\CriticalProjects\CriticalProjectEvaluator;
use App\Services\CriticalProjects\CriticalProjectFindingTracker;
use App\Support\CriticalProjectAccess;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CriticalProjectController extends Controller
{
    private const ORGANIZATION_SESSION_KEY = 'critical_projects.organization_ids.';

    public function openProject(Request $request, int $project): RedirectResponse
    {
        abort_unless($request->user()->can('project.view'), 403);

        $allowedTenantIds = CriticalProjectAccess::organizations($request->user())->pluck('id');
        $target = Project::withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $allowedTenantIds)
            ->whereIn('status', [0, 1])
            ->findOrFail($project);
        abort_unless(CriticalProjectAccess::canViewProject($request->user(), $target), 404);

        if (SystemSetting::multiTenantEnabled() && CurrentTenant::id() !== (int) $target->tenant_id) {
            CurrentTenant::switchTo((int) $target->tenant_id);
        }

        return redirect()->route('critical-projects.index', $request->query())
            ->with('open_project_from_critical_projects', $target->id);
    }

    public function __invoke(Request $request, CriticalProjectEvaluator $evaluator, CriticalProjectFindingTracker $tracker): View
    {
        abort_unless($request->user()->can('project.view'), 403);

        $organizations = CriticalProjectAccess::organizations($request->user());
        $allowedIds = $organizations->pluck('id')->map(fn ($id) => (int) $id);
        $organizationSessionKey = self::ORGANIZATION_SESSION_KEY.$request->user()->id;
        if (! SystemSetting::multiTenantEnabled()) {
            $selectedIds = $allowedIds->values();
        } elseif ($request->has('organization_filter_submitted')) {
            $selectedIds = collect($request->input('organizations', []))
                ->map(fn ($id) => (int) $id)->intersect($allowedIds)->unique()->values();
            $request->session()->put($organizationSessionKey, $selectedIds->all());
        } elseif ($request->session()->has($organizationSessionKey)) {
            $selectedIds = collect($request->session()->get($organizationSessionKey, []))
                ->map(fn ($id) => (int) $id)->intersect($allowedIds)->unique()->values();
        } else {
            $selectedIds = $allowedIds->values();
        }

        $projects = Project::withoutGlobalScope('tenant')
            ->tap(fn ($query) => CriticalProjectAccess::scopeProjects($query, $request->user(), $allowedIds))
            ->whereIn('status', [0, 1])
            ->with([
                'tenant',
                'workflow' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'projectPeople' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'projectPeople.person.calendarEntries', 'projectPeople.functionGroup',
                'projectWorkflowSteps' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'projectWorkflowSteps.workflowStep' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'projectWorkflowSteps.workflowStep.functionGroups',
                'projectMilestones' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'functionGroupHours', 'projectTemplate.functionGroups',
            ])->get();
        $booked = DB::table('job_hours')->whereIn('project_id', $projects->pluck('id'))
            ->selectRaw('project_id, SUM(hours) AS total')->groupBy('project_id')->pluck('total', 'project_id');

        $rows = $projects->map(function (Project $project) use ($evaluator, $booked) {
            $findings = $evaluator->evaluate($project, (float) ($booked[$project->id] ?? 0));
            $currentStep = $project->projectWorkflowSteps
                ->first(fn ($step) => $step->is_current && $step->workflowStep?->workflow_id === $project->workflow_id);

            return ['project' => $project, 'findings' => $findings, 'current_step' => $currentStep?->workflowStep?->title,
                'rank' => (int) ($findings->max('rank') ?? 0)];
        });

        $rows = $tracker->sync($rows, $request->user())
            ->filter(fn ($row) => $row['findings']->isNotEmpty());

        $severity = $request->string('severity')->toString();
        $reason = $request->string('reason')->toString();
        if (in_array($severity, ['blocked', 'critical', 'watch'], true)) {
            $rows = $rows->map(function ($row) use ($severity) {
                $row['findings'] = $row['findings']->where('severity', $severity)->values();
                $row['rank'] = (int) ($row['findings']->max('rank') ?? 0);

                return $row;
            })->filter(fn ($row) => $row['findings']->isNotEmpty());
        }
        if ($evaluator->definitions()->pluck('code')->contains($reason)) {
            $rows = $rows->map(function ($row) use ($reason) {
                $row['findings'] = $row['findings']->where('code', $reason)->values();
                $row['rank'] = (int) ($row['findings']->max('rank') ?? 0);

                return $row;
            })->filter(fn ($row) => $row['findings']->isNotEmpty());
        }

        $rows = $rows->map(function ($row) {
            $row['findings'] = $row['findings']->sortByDesc('rank')->values();
            $row['all_findings'] = $row['findings'];

            return $row;
        });

        // Für eine Aktion im geöffneten Details-Overlay müssen die Befunde
        // noch gerendert werden, auch wenn das Projekt nach dem Ausblenden
        // seines letzten sichtbaren Befunds aus der Tabelle verschwindet.
        $modalRows = $rows->filter(fn ($row) => $selectedIds->contains((int) $row['project']->tenant_id));

        $showHidden = $request->boolean('show_hidden');
        if (! $showHidden) {
            $rows = $rows->map(function ($row) {
                $row['findings'] = $row['findings']->reject(fn ($finding) => $finding['is_hidden'])->values();

                return $row;
            })->filter(fn ($row) => $row['findings']->isNotEmpty());
        }

        $hiddenOtherCount = $rows->reject(fn ($row) => $selectedIds->contains((int) $row['project']->tenant_id))->count();
        $rows = $rows->filter(fn ($row) => $selectedIds->contains((int) $row['project']->tenant_id));
        $sort = $request->string('sort', 'severity')->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $acknowledgementEnabled = SystemSetting::criticalProjectAcknowledgementEnabled();
        $openProjectId = (int) $request->session()->get('open_project_from_critical_projects', 0);
        $sorters = [
            'pn' => fn ($row) => $row['project']->source_pn,
            'title' => fn ($row) => mb_strtolower($row['project']->title),
            'workflow' => fn ($row) => mb_strtolower($row['project']->workflow?->name ?? ''),
            'status' => fn ($row) => (int) $row['project']->status,
            'severity' => fn ($row) => $row['rank'],
        ];
        $sorter = $sorters[$sort] ?? $sorters['severity'];
        $rows = ($direction === 'asc' ? $rows->sortBy($sorter) : $rows->sortByDesc($sorter))->values();

        return view('critical-projects.index', compact(
            'organizations', 'selectedIds', 'rows', 'modalRows', 'hiddenOtherCount', 'severity', 'reason', 'showHidden', 'sort', 'direction', 'evaluator', 'acknowledgementEnabled', 'openProjectId'
        ));
    }
}
