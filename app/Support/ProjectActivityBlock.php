<?php

namespace App\Support;

use App\Enums\ActivityCategory;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use App\Models\UserPreference;

/**
 * Daten für den Reiter "Vorgänge" eines Projekts (Ralf, 2026-10-09): sortierte Liste, Kategorien, Rechte der angemeldeten Person.
 * Gemeinsam genutzt von der ersten Darstellung in den Projektdetails und vom Neuladen nach dem Erfassen, Ändern oder Löschen.
 */
final class ProjectActivityBlock
{
    /**
     * @param  list<string>  $selectedCategories  leer = alle Kategorien sichtbar
     * @return array<string, mixed>
     */
    public static function data(Project $project, User $user, array $selectedCategories = []): array
    {
        $newestFirst = (bool) (UserPreference::configFor((int) $user->id, UserPreference::PROJECT_ACTIVITIES)['newest_first'] ?? true);

        $activities = $project->activities()->with(['user', 'editor'])->get()
            ->sortBy(fn (Activity $activity) => $activity->sortDate().' '.$activity->created_at->format('Y-m-d H:i:s').' '.str_pad((string) $activity->id, 12, '0', STR_PAD_LEFT), SORT_STRING, $newestFirst)
            ->values();

        // "Notiz" steht immer zur Auswahl, damit die Kategorie auch vor dem ersten Eintrag da ist
        $categories = $activities->map(fn (Activity $activity) => $activity->type->category())->push(ActivityCategory::Note)->unique('value')->sortBy('value')->values();
        $selected = array_values(array_intersect($selectedCategories, $categories->pluck('value')->all()));

        return [
            'project' => $project,
            'activities' => $activities,
            'categories' => $categories,
            'selectedCategories' => $selected === [] ? $categories->pluck('value')->all() : $selected,
            'newestFirst' => $newestFirst,
            'canEdit' => $user->can('project.edit'),
            'isAdmin' => AccessLevel::isAdmin($user),
            'userId' => (int) $user->id,
        ];
    }
}
