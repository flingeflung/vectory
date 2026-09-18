<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\VerbundConflictChecker;
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
    public function __construct(
        private readonly VerbundController $verbund,
        private readonly VerbundConflictChecker $conflictChecker,
    ) {}

    /**
     * Dropdown-Inhalt "Meine Projektgruppen" - wiederverwendet vom
     * Übersichts- UND vom Einzelprojekt-Picker (system-fields/-artiges
     * Partial), deshalb als eigener kleiner Fragment-Endpunkt statt in
     * ProjectController.
     */
    public function panel(Request $request): Response
    {
        $groups = ProjectGroup::visibleTo(Auth::user())->withCount(['projects', 'viewers'])->orderBy('name')->get();

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
        $this->abortIfActiveVerbund($group);

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
        $this->abortIfActiveVerbund($group);

        $group->projects()->detach();

        return $this->panel($request);
    }

    /**
     * "Projektverbund" (Ralf, 2026-09-14): Hinzufügen zu einer Verbund-
     * Gruppe macht das Projekt automatisch zum Unterprojekt (live, kein
     * erneutes Öffnen des Verbund-Dialogs nötig) - außer es ist bereits
     * Teil eines ANDEREN Verbunds, dann wird der Hinzufügen-Vorgang ganz
     * abgelehnt.
     */
    public function addProject(Request $request, ProjectGroup $group, Project $project): Response
    {
        $this->authorizeViewer($group);
        abort_unless($project->tenant_id === CurrentTenant::id(), 404);

        if ($group->projects()->where('projects.id', $project->id)->exists()) {
            return $this->panel($request);
        }

        if ($group->is_verbund && $project->verbund_rolle !== null) {
            abort(422, __('Dieses Projekt ist bereits Teil eines anderen Verbunds.'));
        }

        $group->projects()->attach($project->id);

        if ($group->is_verbund) {
            $this->assignToVerbund($group, collect([$project]));
        }

        return $this->panel($request);
    }

    /**
     * Entfernen aus einer Verbund-Gruppe macht das Projekt automatisch
     * wieder zu einem normalen Projekt - außer es ist das Hauptprojekt,
     * das geht nur über "Verbund auflösen" (VerbundController::destroy()),
     * sonst bliebe der Verbund ohne Hauptprojekt zurück.
     */
    public function removeProject(Request $request, ProjectGroup $group, Project $project): Response
    {
        $this->authorizeViewer($group);

        if ($group->is_verbund) {
            abort_if($project->verbund_rolle === 1, 422, __('Das Hauptprojekt kann nicht einzeln entfernt werden - bitte erst den Verbund auflösen.'));

            $project->update(['verbund_rolle' => null, 'hauptprojekt_id' => null]);
            Activity::log($project, ActivityType::VerbundRoleChanged, __('Aus dem Verbund entfernt.'));
        }

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
        $newIds = $ids->diff($existingIds);

        // Projekte, die schon Teil eines ANDEREN Verbunds sind, werden bei
        // einer Verbund-Gruppe stillschweigend übersprungen (nicht die ganze
        // Aktion blockiert - anders als beim Einzel-Hinzufügen, wo der
        // Anwender das eine betroffene Projekt direkt vor Augen hat).
        if ($group->is_verbund) {
            $candidates = Project::query()->whereIn('id', $newIds)->get();
            $conflicting = $this->conflictChecker->projectsWithExistingRole($candidates);
            $newIds = $newIds->diff($conflicting->pluck('id'));
        }

        $group->projects()->attach($newIds);

        if ($group->is_verbund && $newIds->isNotEmpty()) {
            $this->assignToVerbund($group, Project::query()->whereIn('id', $newIds)->get());
        }

        return $this->panel($request);
    }

    /**
     * "Projektverbund" (Ralf, 2026-09-14): Massenentfernen bei einer
     * aktiven Verbund-Gruppe setzt die Verbund-Rolle der entfernten
     * Projekte automatisch zurück - ist das Hauptprojekt mit dabei, würde
     * das den ganzen Verbund auflösen. Statt das stillschweigend zu tun
     * oder die ganze Aktion zu blockieren, fragt der Client vorher nach
     * (siehe removeAllFiltered() in project-group-modal.blade.php) -
     * erkennbar an "confirm_dissolve" im Request. Ohne diese Bestätigung
     * liefert dieser Endpunkt bewusst KEIN HTML-Fragment, sondern JSON mit
     * einem eigenen Status (409), damit der Client den Unterschied zu einem
     * normalen Fehler erkennt.
     */
    public function removeAllFiltered(Request $request, ProjectGroup $group): Response|JsonResponse
    {
        $this->authorizeViewer($group);

        if (! $group->is_verbund) {
            $group->projects()->detach($this->filteredProjectIds($request));

            return $this->panel($request);
        }

        $ids = $this->filteredProjectIds($request);
        $memberIds = $group->projects()->whereIn('projects.id', $ids)->pluck('projects.id');
        $hauptprojekt = $group->projects()->where('verbund_rolle', 1)->first();
        $removesHauptprojekt = $hauptprojekt && $memberIds->contains($hauptprojekt->id);

        if ($removesHauptprojekt && ! $request->boolean('confirm_dissolve')) {
            return response()->json([
                'needs_confirmation' => true,
                'message' => __('Das Hauptprojekt ist auch in dieser Auswahl - würde es entfernt, wird der ganze Verbund aufgelöst. Trotzdem fortfahren?'),
            ], 409);
        }

        if ($removesHauptprojekt) {
            $this->verbund->dissolve($group);
        } else {
            Project::query()->whereIn('id', $memberIds)->get()->each(function (Project $project) {
                $project->update(['verbund_rolle' => null, 'hauptprojekt_id' => null]);
                Activity::log($project, ActivityType::VerbundRoleChanged, __('Aus dem Verbund entfernt.'));
            });
        }

        $group->projects()->detach($memberIds);

        return $this->panel($request);
    }

    /**
     * @param  Collection<int, Project>  $projects
     */
    private function assignToVerbund(ProjectGroup $group, Collection $projects): void
    {
        $hauptprojekt = $group->projects()->where('verbund_rolle', 1)->first();
        if (! $hauptprojekt) {
            return;
        }

        foreach ($projects as $project) {
            $project->update(['verbund_rolle' => 2, 'hauptprojekt_id' => $hauptprojekt->id]);
            Activity::log($project, ActivityType::VerbundRoleChanged, __('Unterprojekt des Verbunds von ":name" geworden.', ['name' => $hauptprojekt->title]));
        }
    }

    /**
     * Teilen: Liste der Nutzer dieses Mandanten + wer die Gruppe aktuell
     * sieht (für die Checkbox-Liste im "Weitergeben"-Overlay).
     */
    public function shareOptions(ProjectGroup $group): Response
    {
        $this->authorizeViewer($group);

        // Admins ANDERER Mandanten bewusst nur mitlisten, wenn der GERADE
        // ANSCHAUENDE Nutzer selbst Heimat-Admin/Super-Admin ist (Ralf,
        // 2026-09-18: "das darf ja wieder nur vom H-Admin aus möglich
        // sein") - sonst könnte ein Admin eines einzelnen Kundekunden-
        // Mandanten seine Projektgruppe (und damit die enthaltenen Projekte)
        // mit dem Admin eines völlig fremden Kunden teilen.
        $tenantId = CurrentTenant::id();
        $user = Auth::user();
        $canReachAllTenants = CurrentTenant::isHomeTenantAdmin($user) || $user->role === 'super_admin';
        $users = User::query()
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)
                ->when($canReachAllTenants, fn ($query) => $query->orWhereIn('role', ['admin', 'super_admin'])))
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

        // Server-seitig dieselbe Grenze wie in shareOptions() erzwingen -
        // die Auswahlliste dort ist nur eine UI-Hilfe, ohne diesen Check
        // könnte ein direkter POST mit einer beliebigen User-ID trotzdem mit
        // jedem Nutzer irgendeines fremden Mandanten teilen.
        $actingUser = $request->user();
        $canReachAllTenants = CurrentTenant::isHomeTenantAdmin($actingUser) || $actingUser->role === 'super_admin';
        $targetAllowed = $user->tenant_id === CurrentTenant::id()
            || ($canReachAllTenants && in_array($user->role, ['admin', 'super_admin'], true));
        abort_unless($targetAllowed, 403);

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
     * "Projektverbund" (Ralf, 2026-09-14): solange die Gruppe eine aktive
     * Haupt-/Unterprojekt-Zuordnung trägt, darf sie weder geleert noch
     * gelöscht werden - beides würde den Verbund-Container ohne "Verbund
     * auflösen" verschwinden lassen. Einzelnes Hinzufügen/Entfernen von
     * Mitgliedern ist dagegen erlaubt (siehe addProject()/removeProject()/
     * addAllFiltered()/removeAllFiltered() - die synchronisieren die
     * Verbund-Rolle live mit, statt die Aktion zu blockieren).
     */
    private function abortIfActiveVerbund(ProjectGroup $group): void
    {
        abort_if($group->projects()->whereNotNull('verbund_rolle')->exists(), 422, __('Diese Gruppe ist Teil eines Verbunds - bitte erst den Verbund auflösen.'));
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
