<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectGroup;
use Illuminate\Support\Collection;

/**
 * "Projektverbund" (Ralf, 2026-09-14): prüft, ob Gruppenmitglieder schon
 * Teil eines ANDEREN Verbunds sind, bevor ein neuer/geänderter Verbund
 * gespeichert werden darf - genutzt sowohl beim Öffnen des Dialogs (Anzeige
 * der Warnung) als auch beim Speichern (serverseitig erneut geprüft, nie
 * nur dem deaktivierten Button im Client vertraut).
 */
class VerbundConflictChecker
{
    /**
     * @return Collection<int, array{project: Project, reason: string}>
     */
    public function conflictsFor(ProjectGroup $group, Collection $members): Collection
    {
        $memberIds = $members->pluck('id');

        return $members
            ->filter(fn (Project $project) => $project->verbund_rolle !== null)
            ->map(function (Project $project) use ($memberIds, $members) {
                if ($project->verbund_rolle === 1) {
                    $foreignUnterprojekte = $project->unterprojekte()->whereNotIn('id', $memberIds)->exists();

                    if (! $foreignUnterprojekte) {
                        return null;
                    }

                    return [
                        'project' => $project,
                        'reason' => __('Ist bereits Hauptprojekt eines anderen Verbunds.'),
                    ];
                }

                // verbund_rolle === 2 (Unterprojekt)
                $isHauptprojektInThisGroup = $project->hauptprojekt_id !== null
                    && $memberIds->contains($project->hauptprojekt_id)
                    && ($members->firstWhere('id', $project->hauptprojekt_id)?->verbund_rolle === 1);

                if ($isHauptprojektInThisGroup) {
                    return null;
                }

                return [
                    'project' => $project,
                    'reason' => __('Ist bereits Unterprojekt eines anderen Verbunds.'),
                ];
            })
            ->filter()
            ->values();
    }
}
