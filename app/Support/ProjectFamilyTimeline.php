<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use Illuminate\Support\Collection;

/**
 * Daten für das Mini-Gantt im Tab "Zeitplan" der Projektdetails (Ralf, 2026-10-03): bei einem
 * Hauptprojekt das Hauptprojekt plus alle Unterprojekte, bei einem Unterprojekt dasselbe
 * (Hauptprojekt plus Geschwister), bei einem Einzelprojekt nur es selbst. Je Zeile Zeitraum und
 * Meilensteine; die Meilensteine sind die aktuell gültigen Workflow-Termine (has_due_date, nur
 * die dem Projekt zugewiesene Workflow-Generation) - dieselbe Regel wie in der Zeiten-
 * Gesamtansicht und der Projektplanung.
 */
final class ProjectFamilyTimeline
{
    /**
     * @return Collection<int, array{id: int, pn: string, title: string, isMain: bool, isCurrent: bool, start: ?string, end: ?string, milestones: array<int, array{date: string, title: string}>}>
     */
    public static function for(Project $project): Collection
    {
        $main = $project->verbund_rolle === 2 ? ($project->hauptprojekt ?? $project) : $project;
        $members = collect([$main]);
        if ($main->verbund_rolle === 1) {
            $members = $members->concat($main->unterprojekte()->orderBy('source_pn')->get());
        }
        $members = $members->unique('id')->values();

        $stepsByProject = ProjectWorkflowStep::query()
            ->whereIn('project_id', $members->pluck('id'))
            ->whereNotNull('due_date')
            ->whereHas('workflowStep', fn ($query) => $query->where('has_due_date', true))
            ->with('workflowStep')
            ->orderBy('due_date')
            ->get()
            ->groupBy('project_id');

        return $members->map(function (Project $member) use ($project, $main, $stepsByProject) {
            $milestones = ($stepsByProject->get($member->id) ?? collect())
                ->filter(fn (ProjectWorkflowStep $step) => $step->workflowStep->workflow_id === $member->workflow_id)
                ->map(fn (ProjectWorkflowStep $step) => [
                    'date' => $step->due_date->format('Y-m-d'),
                    'title' => $step->effectiveMilestoneTitle() ?: $step->workflowStep->title,
                ])
                ->values()
                ->all();

            return [
                'id' => $member->id,
                'pn' => (string) $member->source_pn,
                'title' => (string) $member->title,
                'isMain' => $member->id === $main->id && $member->verbund_rolle === 1,
                'isCurrent' => $member->id === $project->id,
                'start' => $member->start_date?->format('Y-m-d'),
                'end' => $member->end_date?->format('Y-m-d'),
                'milestones' => $milestones,
            ];
        });
    }
}
