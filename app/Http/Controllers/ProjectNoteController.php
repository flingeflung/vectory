<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * "Bemerkungen" und "Änderungsprotokoll" (siehe ProjectNote) -
 * eigenständige Endpunkte statt Teil des großen Projekt-Formulars (Ralf,
 * 2026-09-12, wie schon bei den Projektverknüpfungen: sofort gespeichert,
 * kein Speichern-Button/Freigabe nötig - "wie eine E-Mail, die wird ja
 * auch nicht freigegeben"). store()/destroy() geben nur die betroffene
 * Zeile bzw. 204 zurück, kein Neuladen der ganzen Liste.
 */
class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in([ProjectNote::TYPE_REMARK, ProjectNote::TYPE_CHANGE])],
            'text' => ['required', 'string', 'max:2000'],
            'box_id' => ['required', 'string', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $note = ProjectNote::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'type' => $validated['type'],
            'text' => trim($validated['text']),
            'created_by_user_id' => $request->user()->id,
            'created_at' => now(),
        ]);

        $html = view('projekte.partials.project-note-row', [
            'note' => $note,
            'editable' => true,
            'idPrefix' => $validated['box_id'],
        ])->render();

        return response($html);
    }

    /**
     * Löschen darf nur der Autor selbst oder ein Admin/Super-Admin (Ralf,
     * 2026-09-12: nicht nur Super-Admin) - "role" ist users.role, nicht das
     * granulare Permission-System.
     */
    public function destroy(Request $request, Project $project, ProjectNote $note): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless($note->project_id === $project->id, 404);
        abort_unless(
            $note->created_by_user_id === $request->user()->id || in_array($request->user()->role, ['admin', 'super_admin'], true),
            403
        );

        $note->delete();

        return response('', 204);
    }
}
