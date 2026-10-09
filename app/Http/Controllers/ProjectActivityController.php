<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\UserPreference;
use App\Support\AccessLevel;
use App\Support\ProjectActivityBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vorgänge eines Projekts (Ralf, 2026-10-09, Vorbild Vietto): von Hand erfassen, ändern, löschen und hervorheben. Alle Aktionen liefern
 * den neu gerenderten Block zurück, den der Reiter "Vorgänge" an Ort und Stelle austauscht.
 *
 * Rechte: anlegen und hervorheben darf, wer das Projekt bearbeiten darf (project.edit). Ändern und Löschen betrifft nur von Hand erfasste
 * Vorgänge und darf der Ersteller selbst oder ein Administrator. Automatische Vorgänge bleiben unverändert und lassen sich nur hervorheben.
 * Der Automatismus hebt von sich aus nichts hervor.
 */
class ProjectActivityController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->mayBeOpenedBy($request->user()), 403);

        return $this->block($request, $project);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeEdit($request, $project);
        $data = $this->validated($request);

        Activity::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'type' => ActivityType::Note,
            'message' => $data['message'],
            'is_automatic' => false,
            'occurred_on' => $data['occurred_on'],
            'is_highlighted' => $data['is_highlighted'],
        ]);

        return $this->block($request, $project);
    }

    public function update(Request $request, Project $project, Activity $activity): JsonResponse
    {
        $this->authorizeEdit($request, $project);
        $this->authorizeOwnNote($request, $project, $activity);
        $data = $this->validated($request);

        $activity->update([
            'message' => $data['message'],
            'occurred_on' => $data['occurred_on'],
            'is_highlighted' => $data['is_highlighted'],
            'edited_by' => $request->user()->id,
            'edited_at' => now(),
        ]);

        return $this->block($request, $project);
    }

    public function destroy(Request $request, Project $project, Activity $activity): JsonResponse
    {
        $this->authorizeEdit($request, $project);
        $this->authorizeOwnNote($request, $project, $activity);

        $activity->delete();

        return $this->block($request, $project);
    }

    /** Hervorhebung ein- oder ausschalten - bei jedem Vorgang möglich, auch bei automatischen. */
    public function highlight(Request $request, Project $project, Activity $activity): JsonResponse
    {
        $this->authorizeEdit($request, $project);
        abort_unless($activity->project_id === $project->id, 404);

        $activity->update(['is_highlighted' => $request->boolean('highlighted')]);

        return $this->block($request, $project);
    }

    /** Reihenfolge je Person: neueste zuerst oder älteste zuerst. */
    public function order(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->mayBeOpenedBy($request->user()), 403);

        UserPreference::persist((int) $request->user()->id, UserPreference::PROJECT_ACTIVITIES, ['newest_first' => $request->boolean('newest_first')]);

        return $this->block($request, $project);
    }

    private function authorizeEdit(Request $request, Project $project): void
    {
        abort_unless($project->mayBeOpenedBy($request->user()) && $request->user()->can('project.edit'), 403);
    }

    private function authorizeOwnNote(Request $request, Project $project, Activity $activity): void
    {
        abort_unless($activity->project_id === $project->id, 404);
        abort_unless($activity->isNote(), 403);
        abort_unless(AccessLevel::isAdmin($request->user()) || $activity->user_id === $request->user()->id, 403);
    }

    /**
     * @return array{message: string, occurred_on: string, is_highlighted: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'after:1999-12-31', 'before:2100-01-01'],
        ]);

        return [
            'message' => trim($data['message']),
            'occurred_on' => $data['occurred_on'],
            'is_highlighted' => $request->boolean('is_highlighted'),
        ];
    }

    private function block(Request $request, Project $project): JsonResponse
    {
        // Nach dem Neuladen bleibt die gewählte Filterung erhalten; ohne Angabe sind alle Kategorien sichtbar
        $selected = array_values(array_filter(explode(',', (string) $request->input('categories'))));

        return response()->json(['html' => view('projekte.partials.activities', ProjectActivityBlock::data($project, $request->user(), $selected))->render()]);
    }
}
