<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectConnection;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Projektverknüpfungen" (Ralf, 2026-09-11) - Vietto-Vorbild:
 * projektverbindungen mit zwei freien Richtungs-Bezeichnungen je Zeile
 * (siehe ProjectConnection). Projekt-Auswahl läuft über den bereits
 * vorhandenen Schnellsuche-Endpunkt (ProjectController::quickSearch),
 * kein eigener Such-Endpunkt nötig.
 */
class ProjectConnectionController extends Controller
{
    /**
     * Inhalt des "Verknüpfen"-Modals - eigenständig statt im großen
     * Projekt-Formular verschachtelt (verschachtelte <form>-Elemente
     * reißen im Browser das versteckte _method-Feld ins äußere Formular
     * mit rein, siehe gleiche Anmerkung bei den Mail-Vorlagen). Zeigt
     * Ralf, 2026-09-11 (S2, Vietto-Vorbild "Verbundene Projekte"):
     * bestehende Verknüpfungen UND das Hinzufügen neuer in EINEM Modal,
     * bleibt beim Hinzufügen/Entfernen offen (lädt sich selbst neu) -
     * mehrere Verknüpfungen lassen sich so nacheinander anlegen, ohne
     * das Modal jedes Mal zu schließen und neu zu öffnen.
     */
    public function form(Project $project): View
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        // Autovervollständigung für die Richtungs-Bezeichnungen (Vietto-
        // Vorbild) - bereits verwendete Texte dieses Mandanten, damit sich
        // eine einheitliche Wortwahl einspielt statt jedes Mal neu erfunden
        // zu werden.
        $labelSuggestions = ProjectConnection::query()->where('tenant_id', $project->tenant_id)->get()
            ->flatMap(fn (ProjectConnection $connection) => [$connection->label, $connection->label_reverse])
            ->unique()
            ->sort()
            ->values();

        return view('projekte.partials.connection-add-body', [
            'project' => $project,
            'connections' => $project->connections(),
            'labelSuggestions' => $labelSuggestions,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $validated = $request->validate([
            'related_project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('tenant_id', $project->tenant_id)],
            'label' => ['required', 'string', 'max:255'],
            'label_reverse' => ['required', 'string', 'max:255'],
        ]);

        if ((int) $validated['related_project_id'] === $project->id) {
            throw ValidationException::withMessages(['related_project_id' => __('Ein Projekt kann nicht mit sich selbst verknüpft werden.')]);
        }

        $exists = ProjectConnection::query()
            ->where(fn ($query) => $query->where('project_id', $project->id)->where('related_project_id', $validated['related_project_id']))
            ->orWhere(fn ($query) => $query->where('project_id', $validated['related_project_id'])->where('related_project_id', $project->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['related_project_id' => __('Diese beiden Projekte sind bereits verknüpft.')]);
        }

        ProjectConnection::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'related_project_id' => $validated['related_project_id'],
            'label' => trim($validated['label']),
            'label_reverse' => trim($validated['label_reverse']),
            'created_by_user_id' => $request->user()->id,
        ]);

        return back();
    }

    public function destroy(Project $project, ProjectConnection $connection): RedirectResponse
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless(in_array($project->id, [$connection->project_id, $connection->related_project_id], true), 404);

        $connection->delete();

        return back();
    }
}
