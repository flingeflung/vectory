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
 * (siehe ProjectConnection).
 */
class ProjectConnectionController extends Controller
{
    /**
     * Inhalt des "Verknüpfen"-Modals - eigenständig statt im großen
     * Projekt-Formular verschachtelt (verschachtelte <form>-Elemente
     * reißen im Browser das versteckte _method-Feld ins äußere Formular
     * mit rein, siehe gleiche Anmerkung bei den Mail-Vorlagen).
     *
     * Struktur 1:1 aus Viettos ajax_getpnconnections.php übernommen (Ralf,
     * 2026-09-11, nach mehreren Fehlversuchen meinerseits direkt im
     * Vietto-Quellcode nachgeschaut statt weiter zu raten): EIN Query über
     * ALLE Projekte dieses Mandanten (außer sich selbst), verknüpfte zuerst
     * (angehakt, mit Richtungstext), dann alle anderen (nicht angehakt) -
     * das Suchfeld filtert genau diese eine Liste (beide Abschnitte), ersetzt
     * sie nicht durch eine separate Trefferliste. "q" leer = komplette
     * Liste, wie in Vietto.
     */
    public function form(Project $project, Request $request): View
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $search = trim((string) $request->query('q', ''));

        $query = Project::query()->where('tenant_id', $project->tenant_id)->where('id', '!=', $project->id);
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('source_pn', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }
        $allProjects = $query->orderBy('source_pn')->get(['id', 'source_pn', 'title']);

        $connections = $project->connections()->keyBy('otherProject.id');

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
            'search' => $search,
            'connectedProjects' => $allProjects->filter(fn ($p) => $connections->has($p->id))->values(),
            'otherProjects' => $allProjects->reject(fn ($p) => $connections->has($p->id))->values(),
            'connections' => $connections,
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
