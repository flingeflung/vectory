<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Gruppen und Personenwerte des Projekt-Tabs "Planung" - eine Quelle für die
 * Erstdarstellung (planning.blade.php) und das Live-Nachladen nach einer
 * Änderung der Projektbeteiligten (ProjectController::planningGroups()).
 */
final class ProjectPlanningGroups
{
    /**
     * @param  Collection<int, \App\Models\FunctionGroup>  $allFunctionGroups
     * @return array{planningGroups: Collection, entriesByGroup: Collection, plannedValues: Collection, personValues: Collection}
     */
    public static function for(Project $project, Collection $allFunctionGroups): array
    {
        $effectiveGroupHours = $project->functionGroupHours->isNotEmpty()
            ? $project->functionGroupHours->pluck('pivot.planned_hours', 'id')
            : ($project->projectTemplate?->functionGroups?->pluck('pivot.planned_hours', 'id') ?? collect());
        $entriesByGroup = $project->projectPeople->groupBy('function_group_id');
        $planningGroups = $allFunctionGroups->filter(fn ($group) => $effectiveGroupHours->has($group->id) || $entriesByGroup->has($group->id));
        $plannedValues = $planningGroups->mapWithKeys(fn ($group) => [(string) $group->id => (float) ($effectiveGroupHours->get($group->id) ?? 0)]);
        $personValues = $planningGroups->mapWithKeys(fn ($group) => [
            (string) $group->id => ($entriesByGroup->get($group->id) ?? collect())->mapWithKeys(fn ($entry) => [
                (string) $entry->person_id => (float) ($entry->planned_hours ?? 0),
            ]),
        ]);

        return compact('planningGroups', 'entriesByGroup', 'plannedValues', 'personValues');
    }
}
