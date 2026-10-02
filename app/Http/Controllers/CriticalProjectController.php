<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\CriticalProjects\CriticalProjectEvaluator;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CriticalProjectController extends Controller
{
    public function __invoke(Request $request, CriticalProjectEvaluator $evaluator): View
    {
        abort_unless($request->user()->can('project.view'), 403);

        $organizations = SystemSetting::multiTenantEnabled()
            ? CurrentTenant::availableTenants()
            : Tenant::query()->whereKey(CurrentTenant::id())->get();
        $allowedIds = $organizations->pluck('id')->map(fn ($id) => (int) $id);
        $selectedIds = collect($request->input('organizations', [CurrentTenant::id()]))
            ->map(fn ($id) => (int) $id)->intersect($allowedIds)->unique()->values();
        if ($selectedIds->isEmpty()) {
            $selectedIds = collect([(int) CurrentTenant::id()])->intersect($allowedIds)->values();
        }

        $projects = Project::withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $allowedIds)
            ->whereIn('status', [0, 1])
            ->with([
                'tenant', 'workflow', 'projectPeople.person', 'projectPeople.functionGroup',
                'projectWorkflowSteps.workflowStep.functionGroups',
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
        })->filter(fn ($row) => $row['findings']->isNotEmpty());

        $severity = $request->string('severity')->toString();
        $reason = $request->string('reason')->toString();
        if (in_array($severity, ['blocked', 'critical', 'watch'], true)) {
            $rows = $rows->filter(function ($row) use ($severity) {
                $row['findings'] = $row['findings']->where('severity', $severity)->values();
                $row['rank'] = (int) ($row['findings']->max('rank') ?? 0);

                return $row['findings']->isNotEmpty();
            });
        }
        if ($evaluator->definitions()->pluck('code')->contains($reason)) {
            $rows = $rows->filter(function ($row) use ($reason) {
                $row['findings'] = $row['findings']->where('code', $reason)->values();
                $row['rank'] = (int) ($row['findings']->max('rank') ?? 0);

                return $row['findings']->isNotEmpty();
            });
        }

        $hiddenOtherCount = $rows->reject(fn ($row) => $selectedIds->contains((int) $row['project']->tenant_id))->count();
        $rows = $rows->filter(fn ($row) => $selectedIds->contains((int) $row['project']->tenant_id));
        $sort = $request->string('sort', 'severity')->toString();
        $direction = $request->string('direction', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
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
            'organizations', 'selectedIds', 'rows', 'hiddenOtherCount', 'severity', 'reason', 'sort', 'direction', 'evaluator'
        ));
    }
}
