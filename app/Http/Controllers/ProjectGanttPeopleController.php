<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectGanttPeopleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_ids' => ['required', 'array', 'max:500'],
            'project_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $projects = Project::query()
            ->whereIn('id', $validated['project_ids'])
            ->with([
                'projectPeople.person.department' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'projectPeople.person.company' => fn ($query) => $query->withoutGlobalScope('tenant'),
            ])
            ->get();

        $peopleByProject = $projects->mapWithKeys(fn (Project $project) => [
            $project->id => $project->projectPeople
                ->filter(fn ($entry) => $entry->person !== null)
                ->unique('person_id')
                ->map(fn ($entry) => [
                    'id' => $entry->person_id,
                    'name' => $entry->person->fullName(),
                    'inactive' => ! $entry->person->active,
                    'affiliation' => $entry->person->department?->name ?: $entry->person->company?->name,
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ]);

        return response()->json(['people_by_project' => $peopleByProject]);
    }
}
