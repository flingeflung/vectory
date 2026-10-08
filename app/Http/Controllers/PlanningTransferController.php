<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Services\PlanningTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Planung übertragen" (Ralf, 2026-10-08): Planung eines Projekts an eine Gruppe (Verbund) oder ein Einzelprojekt senden - oder von
 * einem anderen Projekt holen. Globales Overlay wie "Termine berechnen": Formular, danach Bericht je Zielprojekt.
 */
class PlanningTransferController extends Controller
{
    public function form(Request $request, Project $project): View
    {
        abort_unless($request->user()->can('project.edit'), 403);

        $groups = ProjectGroup::query()->visibleTo($request->user())->where('tenant_id', $project->tenant_id)
            ->withCount('projects')->orderBy('name')->get();
        $defaultGroup = $project->projectGroups()->where('is_verbund', true)->first();
        $projects = Project::query()->where('tenant_id', $project->tenant_id)->whereKeyNot($project->id)
            ->orderByDesc('source_pn')->limit(5000)->get(['id', 'source_pn', 'title'])
            ->map(fn (Project $other) => ['id' => $other->id, 'label' => $other->source_pn.' – '.$other->title])->values();

        return view('projekte.partials.planning-transfer-body', [
            'project' => $project,
            'groups' => $groups,
            'defaultGroupId' => $defaultGroup?->id,
            'projectOptions' => $projects,
            'parts' => $this->allowedParts($request),
        ]);
    }

    public function run(Request $request, Project $project): View
    {
        abort_unless($request->user()->can('project.edit'), 403);

        $allowed = $this->allowedParts($request);
        $validated = $request->validate([
            'direction' => ['required', 'in:send,fetch'],
            'scope' => ['required', 'in:group,single'],
            'group_id' => ['nullable', 'integer'],
            'include_main' => ['nullable', 'boolean'],
            'other_project_id' => ['nullable', 'integer'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*' => ['string'],
            'keep_existing' => ['nullable', 'boolean'],
        ]);
        $parts = array_values(array_intersect($validated['parts'], array_keys($allowed)));
        abort_if($parts === [], 422, __('Bitte mindestens einen Bereich auswählen, den Sie ändern dürfen.'));
        $keepExisting = (bool) ($validated['keep_existing'] ?? false);

        $pairs = $this->resolvePairs($request, $project, $validated);
        $transfer = app(PlanningTransfer::class);

        $rows = [];
        foreach ($pairs as [$source, $target]) {
            if ($target->tenant_id !== $source->tenant_id) {
                $rows[] = ['target' => $target, 'results' => [['part' => '', 'status' => 'skipped', 'note' => __('Anderer Organisationsbereich, Übertragung nicht möglich.')]]];

                continue;
            }
            $results = DB::transaction(fn () => $transfer->transfer($source->fresh(), $target->fresh(), $parts, $keepExisting));
            $rows[] = ['target' => $target, 'results' => $results];
        }

        return view('projekte.partials.planning-transfer-report', [
            'project' => $project,
            'rows' => $rows,
            'direction' => $validated['direction'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return Collection<int, array{0: Project, 1: Project}> Paare aus Quelle und Ziel
     */
    private function resolvePairs(Request $request, Project $project, array $validated): Collection
    {
        if ($validated['direction'] === 'fetch') {
            $source = Project::query()->find((int) ($validated['other_project_id'] ?? 0));
            abort_if($source === null || $source->is($project), 422, __('Bitte ein anderes Projekt auswählen.'));

            return collect([[$source, $project]]);
        }

        if ($validated['scope'] === 'single') {
            $target = Project::query()->find((int) ($validated['other_project_id'] ?? 0));
            abort_if($target === null || $target->is($project), 422, __('Bitte ein anderes Projekt auswählen.'));

            return collect([[$project, $target]]);
        }

        $group = ProjectGroup::query()->find((int) ($validated['group_id'] ?? 0));
        abort_if($group === null, 422, __('Bitte eine Gruppe auswählen.'));
        $group->authorizeViewer();
        $includeMain = (bool) ($validated['include_main'] ?? false);

        return $group->projects()->get()
            ->reject(fn (Project $member) => $member->is($project) || (! $includeMain && (int) $member->verbund_rolle === 1))
            ->sortBy('source_pn')->values()
            ->map(fn (Project $member) => [$project, $member]);
    }

    /** @return array<string, string> Bereiche, die der Benutzer ändern darf */
    private function allowedParts(Request $request): array
    {
        $user = $request->user();

        return collect(PlanningTransfer::parts())->filter(fn ($label, $key) => match ($key) {
            PlanningTransfer::DURATIONS, PlanningTransfer::MILESTONES => $user->can('workflow_step.due_date'),
            PlanningTransfer::PLANNED_HOURS => $user->can('planning.view'),
            default => true,
        })->all();
    }
}
