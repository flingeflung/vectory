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
        $currentHauptprojekt = $members->firstWhere('verbund_rolle', 1);

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
                'currentHauptprojektId' => $members->firstWhere('verbund_rolle', 1)?->id,
                'hasActiveVerbund' => $members->contains('verbund_rolle', 1),
                'formError' => ! $selected && $conflicts->isEmpty() ? __('Bitte ein Hauptprojekt auswählen.') : null,
            ])->setStatusCode(422);
        }

        DB::transaction(function () use ($members, $selected) {
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
        });

        return $this->panel($group);
    }

    public function destroy(ProjectGroup $group): Response
    {
        $group->authorizeViewer();

        DB::transaction(function () use ($group) {
            $members = $group->projects()->whereNotNull('verbund_rolle')->get();

            foreach ($members as $project) {
                $project->update(['verbund_rolle' => null, 'hauptprojekt_id' => null]);
                Activity::log($project, ActivityType::VerbundDissolved, __('Verbund aufgelöst.'));
            }
        });

        return $this->panel($group);
    }
}
