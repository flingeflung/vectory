<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Meine Projektgruppen" (Ralf, 2026-09-13, analog Viettos gruppen/
 * gruppen_cx/gruppen_pers_cx, Analyse siehe Backlog-Memory) - persönliche,
 * aber echt TEILBARE Projektlisten. Kein Besitzer-Konzept: project_group_
 * user entscheidet allein, wer eine Gruppe sieht/bearbeitet, alle
 * Sichtbaren haben dieselben Rechte (wie im Vorbild).
 *
 * Zwei Vietto-Bugs bewusst NICHT übernommen: (1) FK mit cascadeOnDelete auf
 * project_id statt Freitext-PN-Verweis - keine verwaisten Zeilen beim
 * Projekt-Löschen mehr. (2) Löschen einer geteilten Gruppe nennt jetzt
 * explizit, wie viele andere Personen sie noch sehen (Konvention:
 * "Konsequenz explizit nennen" aus CLAUDE.md), statt kommentarlos für alle
 * zu verschwinden.
 */
class ProjectGroupController extends Controller
{
    /**
     * Dropdown-Inhalt "Meine Projektgruppen" - wiederverwendet vom
     * Übersichts- UND vom Einzelprojekt-Picker (system-fields/-artiges
     * Partial), deshalb als eigener kleiner Fragment-Endpunkt statt in
     * ProjectController.
     */
    public function panel(Request $request): Response
    {
        $groups = Auth::user()->projectGroups()->withCount(['projects', 'viewers'])->orderBy('name')->get();

        $project = $request->integer('project_id')
            ? Project::query()->findOrFail($request->integer('project_id'))
            : null;
        abort_if($project && $project->tenant_id !== CurrentTenant::id(), 404);

        return response()->view('projekte.partials.project-group-panel', [
            'groups' => $groups,
            'project' => $project,
            'memberGroupIds' => $project ? $project->projectGroups()->pluck('project_groups.id') : collect(),
        ]);
    }

    /**
     * Projekt-IDs, die aktuell in dieser Gruppe sind - fürs Vorbelegen der
     * Checkbox-Spalte in der Übersicht, wenn eine Gruppe im Dropdown
     * gewählt wird (analog Viettos grp_markiere()).
     */
    public function memberIds(ProjectGroup $group): JsonResponse
    {
        $this->authorizeViewer($group);

        return response()->json($group->projects()->pluck('projects.id'));
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $group = ProjectGroup::query()->create(['tenant_id' => CurrentTenant::id(), 'name' => $name]);
        $group->viewers()->attach(Auth::id());

        // Neue Gruppe soll direkt ausgewählt sein (Ralf, 2026-09-13) - der
        // Header transportiert die neue ID zum Client, der Rest der Antwort
        // bleibt das normale Panel-Fragment.
        return $this->panel($request)->header('X-Created-Group-Id', (string) $group->id);
    }

    public function update(Request $request, ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $group->update(['name' => $name]);

        return $this->panel($request);
    }

    /**
     * Löscht die Gruppe komplett - für ALLE Personen, die sie sehen, nicht
     * nur für mich (siehe Klassen-Docblock). Die Anzahl betroffener anderer
     * Personen kommt vom Client (Konfirm-Dialog vorher), hier nur
     * serverseitig durchgesetzt: keine Berechtigungsstufe nötig, jeder
     * Sichtbare darf löschen (wie im Vietto-Vorbild) - lediglich die
     * Konsequenz wird jetzt vorher explizit genannt statt verschwiegen.
     */
    public function destroy(Request $request, ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $group->delete();

        return $this->panel($request);
    }

    /**
     * "Diese Gruppe verlassen" - entfernt nur mich. Bin ich die letzte
     * Person mit Zugriff, wird die Gruppe komplett gelöscht (kein
     * herrenloses Datenfragment).
     */
    public function leave(Request $request, ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $group->viewers()->detach(Auth::id());
        if ($group->viewers()->count() === 0) {
            $group->delete();
        }

        return $this->panel($request);
    }

    public function clear(Request $request, ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $group->projects()->detach();

        return $this->panel($request);
    }

    public function addProject(Request $request, ProjectGroup $group, Project $project): Response
    {
        $this->authorizeViewer($group);
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        if (! $group->projects()->where('projects.id', $project->id)->exists()) {
            $group->projects()->attach($project->id);
        }

        return $this->panel($request);
    }

    public function removeProject(Request $request, ProjectGroup $group, Project $project): Response
    {
        $this->authorizeViewer($group);

        $group->projects()->detach($project->id);

        return $this->panel($request);
    }

    /**
     * "Alle angezeigten Projekte hinzufügen/entfernen" - bezieht sich auf
     * ALLE gefilterten Treffer, nicht nur die aktuelle Seite (Ralf,
     * 2026-09-13, explizite Entscheidung - Vietto selbst löst das nicht
     * sauber, siehe Analyse).
     */
    public function addAllFiltered(Request $request, ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $ids = $this->filteredProjectIds($request);
        $existingIds = $group->projects()->whereIn('projects.id', $ids)->pluck('projects.id');
        $group->projects()->attach($ids->diff($existingIds));

        return $this->panel($request);
    }

    public function removeAllFiltered(Request $request, ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $group->projects()->detach($this->filteredProjectIds($request));

        return $this->panel($request);
    }

    /**
     * Teilen: Liste der Nutzer dieses Mandanten + wer die Gruppe aktuell
     * sieht (für die Checkbox-Liste im "Weitergeben"-Overlay).
     */
    public function shareOptions(ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        $tenantId = CurrentTenant::id();
        $users = User::query()
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->orWhereIn('role', ['admin', 'super_admin']))
            ->orderBy('name')
            ->get();

        return response()->view('projekte.partials.project-group-share', [
            'group' => $group,
            'users' => $users,
            'viewerIds' => $group->viewers()->pluck('users.id'),
        ]);
    }

    public function share(Request $request, ProjectGroup $group, User $user): Response
    {
        $this->authorizeViewer($group);

        if (! $group->viewers()->where('users.id', $user->id)->exists()) {
            $group->viewers()->attach($user->id);
        }

        return $this->shareOptions($group);
    }

    public function unshare(ProjectGroup $group, User $user): Response
    {
        $this->authorizeViewer($group);

        $group->viewers()->detach($user->id);
        if ($group->viewers()->count() === 0) {
            $group->delete();
        }

        return $this->shareOptions($group);
    }

    /**
     * "Nur diese Gruppe anzeigen" - setzt den Übersichtsfilter. Ralf-Bug-
     * Report, 2026-09-13: das Gruppieren-Panel schloss sich dabei einfach
     * (normaler Seitenaufruf) und die Häkchen-Spalte verschwand - "reopen_
     * group" lässt die Übersicht das Panel mit derselben Gruppe direkt
     * wieder öffnen, analog zum bestehenden "reopen_filter" fürs
     * Projektfilter-Modal.
     */
    public function showInOverview(ProjectGroup $group): RedirectResponse
    {
        $this->authorizeViewer($group);

        return redirect()->route('projekte', ['filter' => ['project_group_id' => $group->id], 'reopen_group' => $group->id]);
    }

    private function authorizeViewer(ProjectGroup $group): void
    {
        $group->authorizeViewer();
    }

    /**
     * @return Collection<int, int>
     */
    private function filteredProjectIds(Request $request): Collection
    {
        $filters = collect($request->input('filter', []))->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])->all();

        return app(ProjectController::class)->filteredIdsForGroups($filters);
    }
}
