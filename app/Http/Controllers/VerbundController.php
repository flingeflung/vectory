<?php

namespace App\Http\Controllers;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Support\VerbundConflictChecker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * "Projektverbund" (Ralf, 2026-09-14): Hauptprojekt + Unterprojekte
 * innerhalb einer bestehenden Projektgruppe - die Gruppe bleibt der
 * dauerhafte Container, verbund_rolle/hauptprojekt_id sitzen aber direkt am
 * Projekt (siehe Project::hauptprojekt()/unterprojekte()), damit sie auch
 * ganz ohne offene Gruppe in der Übersicht sichtbar sind.
 */
class VerbundController extends Controller
{
    public function __construct(private readonly VerbundConflictChecker $conflictChecker) {}

    public function panel(ProjectGroup $group): Response
    {
        $group->authorizeViewer();

        $members = $group->projects()->with('hauptprojekt')->orderBy('source_pn')->get();
        $conflicts = $this->conflictChecker->conflictsFor($group, $members);
        // Nur relevant, wenn DIESE Gruppe selbst der Verbund ist (Ralf,
        // 2026-09-27) - sonst würde ein Mitglied, das nur zufällig auch in
        // einer ANDEREN, echten Verbund-Gruppe Hauptprojekt ist, hier fälschlich
        // als "aktueller Verbund dieser Gruppe" auftauchen (gleicher Fehler wie
        // bei ProjectGroupController::abortIfActiveVerbund() zuvor).
        $currentHauptprojekt = $group->is_verbund ? $members->firstWhere('verbund_rolle', 1) : null;

        return response()->view('projekte.partials.verbund-panel', [
            'group' => $group,
            'members' => $members,
            'conflicts' => $conflicts,
            'currentHauptprojektId' => $currentHauptprojekt?->id,
            'hasActiveVerbund' => $currentHauptprojekt !== null,
        ]);
    }

    public function store(Request $request, ProjectGroup $group): Response
    {
        $group->authorizeViewer();

        $members = $group->projects()->with('hauptprojekt')->orderBy('source_pn')->get();
        $conflicts = $this->conflictChecker->conflictsFor($group, $members);

        $selectedId = $request->integer('hauptprojekt_id');
        $selected = $members->firstWhere('id', $selectedId);

        if ($conflicts->isNotEmpty() || ! $selected) {
            return response()->view('projekte.partials.verbund-panel', [
                'group' => $group,
                'members' => $members,
                'conflicts' => $conflicts,
                'currentHauptprojektId' => $group->is_verbund ? $members->firstWhere('verbund_rolle', 1)?->id : null,
                'hasActiveVerbund' => $group->is_verbund && $members->contains('verbund_rolle', 1),
                'formError' => ! $selected && $conflicts->isEmpty() ? __('Bitte ein Hauptprojekt auswählen.') : null,
            ])->setStatusCode(422);
        }

        DB::transaction(function () use ($group, $members, $selected) {
            foreach ($members as $project) {
                if ($project->id === $selected->id) {
                    $project->update(['verbund_rolle' => 1, 'hauptprojekt_id' => null]);
                } else {
                    $project->update(['verbund_rolle' => 2, 'hauptprojekt_id' => $selected->id]);
                }

                if ($project->wasChanged(['verbund_rolle', 'hauptprojekt_id'])) {
                    Activity::log($project, ActivityType::VerbundRoleChanged, $project->id === $selected->id
                        ? __('Zum Hauptprojekt eines Verbunds gemacht.')
                        : __('Unterprojekt des Verbunds von ":name" geworden.', ['name' => $selected->title]));
                }
            }

            $group->update(['is_verbund' => true]);
        });

        return $this->panel($group);
    }

    public function destroy(ProjectGroup $group): Response
    {
        $group->authorizeViewer();
        // Verteidigung gegen einen direkten Aufruf ohne den (jetzt korrekt
        // ausgeblendeten) Button: ohne diese Sperre würde dissolve() unten
        // ALLE Mitglieder mit gesetzter Verbund-Rolle zurücksetzen, auch
        // wenn die eigentlich zu einem ganz anderen Verbund gehören.
        abort_unless($group->is_verbund, 404);

        $this->dissolve($group);

        return $this->panel($group);
    }

    /**
     * Setzt Verbund-Rolle/Hauptprojekt-Zuweisung aller Mitglieder zurück und
     * markiert die Gruppe wieder als normale Gruppe - eigene Methode, weil
     * ProjectGroupController::removeAllFiltered() dieselbe Logik braucht
     * (Ralf, 2026-09-14: Massenentfernen, das auch das Hauptprojekt
     * einschließt, löst nach Bestätigung den ganzen Verbund auf).
     */
    public function dissolve(ProjectGroup $group): void
    {
        DB::transaction(function () use ($group) {
            $members = $group->projects()->whereNotNull('verbund_rolle')->get();

            foreach ($members as $project) {
                $project->update(['verbund_rolle' => null, 'hauptprojekt_id' => null]);
                Activity::log($project, ActivityType::VerbundDissolved, __('Verbund aufgelöst.'));
            }

            $group->update(['is_verbund' => false]);
        });
    }
}
