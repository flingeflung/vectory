<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Project;
use App\Models\RecentlyViewedProject;
use App\Models\Task;
use App\Support\DashboardTileCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $activeTiles = DashboardTileCatalog::activeFor($user);

        return view('dashboard', [
            'allTiles' => DashboardTileCatalog::available(),
            'activeTiles' => $activeTiles,
            'recentProjects' => in_array('recent_projects', $activeTiles, true)
                ? RecentlyViewedProject::query()
                    ->where('user_id', $user->id)
                    ->with('project')
                    ->orderByDesc('viewed_at')
                    ->limit(RecentlyViewedProject::LIMIT)
                    ->get()
                : collect(),
            'favoriteProjects' => in_array('favorites', $activeTiles, true)
                ? Project::query()
                    ->whereIn('id', Favorite::query()->where('user_id', $user->id)->pluck('project_id'))
                    ->orderBy('source_pn')
                    ->get()
                : collect(),
            'tasks' => in_array('tasks', $activeTiles, true) && $user->person
                ? Task::query()
                    ->where('person_id', $user->person->id)
                    ->with(['project', 'projectWorkflowStep.workflowStep'])
                    ->get()
                    // Eine Person kann im selben Schritt mehreren Funktionsgruppen
                    // angehören (mehrere Task-Zeilen) - pro Projekt trotzdem nur
                    // eine Zeile in der Kachel anzeigen.
                    ->unique('project_id')
                    ->sortBy(fn (Task $task) => $task->project?->source_pn)
                : collect(),
        ]);
    }

    public function updateLayout(Request $request): RedirectResponse
    {
        DashboardTileCatalog::persistActiveFor($request->user(), array_values($request->input('tiles', [])));

        return redirect()->route('dashboard');
    }

    public function removeRecent(Request $request, RecentlyViewedProject $recentlyViewedProject): RedirectResponse
    {
        abort_unless($recentlyViewedProject->user_id === $request->user()->id, 404);

        $recentlyViewedProject->delete();

        return redirect()->route('dashboard');
    }
}
