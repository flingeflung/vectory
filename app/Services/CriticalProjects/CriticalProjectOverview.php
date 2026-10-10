<?php

namespace App\Services\CriticalProjects;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CriticalProjectAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Berechnet die Zeilen der Kritischen Projekte (Projekt, Befunde, aktueller Schritt) - gemeinsame Quelle für die Liste
 * und für die Meldung auf der Startseite.
 */
class CriticalProjectOverview
{
    public function __construct(
        private readonly CriticalProjectEvaluator $evaluator,
        private readonly CriticalProjectFindingTracker $tracker,
    ) {}

    /**
     * @param  Collection<int, int>  $organizationIds
     * @param  bool  $onlyOwn  true = nur Projekte, bei denen der Benutzer als Person beteiligt ist (auch mit dem Recht „alle Projekte sehen“)
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(User $user, Collection $organizationIds, bool $onlyOwn = false): Collection
    {
        $query = Project::withoutGlobalScope('tenant');
        if ($onlyOwn) {
            $query->whereIn('tenant_id', $organizationIds)
                ->whereHas('projectPeople', fn ($people) => $people->withoutGlobalScope('tenant')->where('person_id', $user->person_id ?? 0));
        } else {
            CriticalProjectAccess::scopeProjects($query, $user, $organizationIds);
        }

        $projects = $query
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

        $rows = $projects->map(function (Project $project) use ($booked) {
            $findings = $this->evaluator->evaluate($project, (float) ($booked[$project->id] ?? 0));
            $currentStep = $project->projectWorkflowSteps
                ->first(fn ($step) => $step->is_current && $step->workflowStep?->workflow_id === $project->workflow_id);

            return ['project' => $project, 'findings' => $findings, 'current_step' => $currentStep?->workflowStep?->title,
                'rank' => (int) ($findings->max('rank') ?? 0)];
        });

        return $this->tracker->sync($rows, $user)
            ->filter(fn ($row) => $row['findings']->isNotEmpty());
    }

    /**
     * Zahlen für die Meldung auf der Startseite: eigene Projekte mit mindestens einem Befund, den der Benutzer weder
     * ausgeblendet noch (bei eingeschalteter Funktion) zur Kenntnis genommen hat.
     *
     * @param  Collection<int, int>  $organizationIds
     * @return array{projects: int, blocked: int}
     */
    public function summaryForUser(User $user, Collection $organizationIds): array
    {
        $acknowledgementEnabled = SystemSetting::criticalProjectAcknowledgementEnabled();

        $visible = $this->rows($user, $organizationIds, true)
            ->map(fn ($row) => $row['findings']->reject(
                fn ($finding) => $finding['is_hidden'] || ($acknowledgementEnabled && $finding['state']?->acknowledged_at)
            ))
            ->filter(fn ($findings) => $findings->isNotEmpty());

        return [
            'projects' => $visible->count(),
            'blocked' => $visible->filter(fn ($findings) => $findings->contains('severity', 'blocked'))->count(),
        ];
    }
}
