<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectConnection;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     * Ralf, 2026-09-11: bei ~6700 Projekten (Sanitär) sind "Andere Projekte"
     * in einem Rutsch zu viele - 500 laden, weitere per Nachladen beim
     * Scrollen ans Listenende (siehe moreOtherProjects()), analog
     * unendlichem Scrollen. "Verknüpfte Projekte" bleibt unpaginiert (in
     * der Praxis immer klein).
     */
    private const PAGE_SIZE = 500;

    /**
     * Inhalt des "Verknüpfen"-Modals - eigenständig statt im großen
     * Projekt-Formular verschachtelt (verschachtelte <form>-Elemente
     * reißen im Browser das versteckte _method-Feld ins äußere Formular
     * mit rein, siehe gleiche Anmerkung bei den Mail-Vorlagen).
     *
     * Struktur 1:1 aus Viettos ajax_getpnconnections.php übernommen (Ralf,
     * 2026-09-11, nach mehreren Fehlversuchen meinerseits direkt im
     * Vietto-Quellcode nachgeschaut statt weiter zu raten): verknüpfte
     * Projekte zuerst (angehakt, mit Richtungstext), dann alle anderen
     * (nicht angehakt) - das Suchfeld filtert diese eine Liste (beide
     * Abschnitte), ersetzt sie nicht durch eine separate Trefferliste. "q"
     * leer = komplette Liste, wie in Vietto (nur eben paginiert, s. o.).
     */
    public function form(Project $project, Request $request): View
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $search = trim((string) $request->query('q', ''));

        $connections = $project->connections()->keyBy('otherProject.id');
        $connectedIds = $connections->keys()->all();

        $connectedQuery = Project::query()->where('tenant_id', $project->tenant_id)->whereIn('id', $connectedIds);
        $this->applySearch($connectedQuery, $search);
        $connectedProjects = $connectedQuery->orderBy('source_pn')->get(['id', 'source_pn', 'title']);

        $otherQuery = Project::query()->where('tenant_id', $project->tenant_id)
            ->where('id', '!=', $project->id)
            ->whereNotIn('id', $connectedIds);
        $this->applySearch($otherQuery, $search);
        $otherProjects = $otherQuery->orderBy('source_pn')->limit(self::PAGE_SIZE)->get(['id', 'source_pn', 'title']);

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
            'connectedProjects' => $connectedProjects,
            'otherProjects' => $otherProjects,
            'otherHasMore' => $otherProjects->count() === self::PAGE_SIZE,
            'pageSize' => self::PAGE_SIZE,
            'connections' => $connections,
            'labelSuggestions' => $labelSuggestions,
        ]);
    }

    /**
     * Nachladen weiterer "Andere Projekte" beim Scrollen ans Listenende -
     * gibt nur die Zeilen-Fragmente zurück (kein ganzes Modal), "Weitere
     * vorhanden?" steckt im X-Has-More-Header statt im HTML selbst.
     */
    public function moreOtherProjects(Project $project, Request $request): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        $search = trim((string) $request->query('q', ''));
        $offset = max(0, $request->integer('offset'));

        $connectedIds = $project->connections()->pluck('otherProject.id')->all();

        $otherQuery = Project::query()->where('tenant_id', $project->tenant_id)
            ->where('id', '!=', $project->id)
            ->whereNotIn('id', $connectedIds);
        $this->applySearch($otherQuery, $search);
        $otherProjects = $otherQuery->orderBy('source_pn')->skip($offset)->take(self::PAGE_SIZE)->get(['id', 'source_pn', 'title']);

        $html = view('projekte.partials.connection-other-project-rows', [
            'project' => $project,
            'otherProjects' => $otherProjects,
        ])->render();

        return response($html)->header('X-Has-More', $otherProjects->count() === self::PAGE_SIZE ? '1' : '0');
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('source_pn', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        }
    }

    /**
     * Gibt NUR die neu entstandene "Verknüpfte Projekte"-Zeile als HTML-
     * Fragment zurück (statt die komplette Liste neu zu laden/rendern) -
     * bei ~6700 Projekten (Sanitär) war das komplette Neuladen nach jedem
     * Hinzufügen spürbar langsam (Ralf-Bug-Report). Client entfernt die
     * entsprechende "Andere Projekte"-Zeile selbst (siehe
     * connection-add-body.blade.php, confirmAdd()).
     */
    public function store(Request $request, Project $project): Response
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

        $label = trim($validated['label']);

        $connection = ProjectConnection::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'related_project_id' => $validated['related_project_id'],
            'label' => $label,
            'label_reverse' => trim($validated['label_reverse']),
            'created_by_user_id' => $request->user()->id,
        ]);

        $relatedProject = Project::query()->findOrFail($validated['related_project_id']);

        $html = view('projekte.partials.connection-connected-project-row', [
            'p' => $relatedProject,
            'connectionId' => $connection->id,
            'label' => $label,
        ])->render();

        return response($html);
    }

    /**
     * Nur ein 204 - der Client entfernt die betroffene Zeile selbst, kein
     * Neuladen der Liste nötig (gleicher Grund wie bei store()).
     */
    public function destroy(Project $project, ProjectConnection $connection): Response
    {
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);
        abort_unless(in_array($project->id, [$connection->project_id, $connection->related_project_id], true), 404);

        $connection->delete();

        return response('', 204);
    }
}
