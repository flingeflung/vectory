<?php

namespace App\Http\Controllers;

use App\Models\ProjectFilterSet;
use App\Support\ProjectFilterCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Benannte Projektfilter-Sets (Ralf, 2026-09-26: "wie beim Anzeigefilter
 * bereits eingebaut", aber als eigene, unabhängige Sets - siehe
 * ProjectFilterSet-Migration). Gleiches Grundmuster wie
 * DisplayFilterController, aber ohne dessen carryOverFilterConfig()-Sonderfall
 * (kein zweiter Config-Zweck wie dort die Spalten, hier ist filter_fields/
 * filter_values der einzige Inhalt).
 */
class ProjectFilterSetController extends Controller
{
    /**
     * "Speichern" des aktuell aktiven Sets - übernimmt die gerade im
     * Projektfilter-Formular gewählten Felder/Werte unter demselben Namen.
     */
    public function update(Request $request): RedirectResponse
    {
        $set = $this->ownedSet($request, (int) $request->input('set_id'));

        $set->update(['config' => $this->configFromRequest($request)]);

        return back()->with('status', 'projektfilter-saved');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $user = $request->user();

        ProjectFilterSet::where('user_id', $user->id)->update(['is_active' => false]);

        $set = ProjectFilterSet::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'config' => $this->configFromRequest($request),
            'is_active' => true,
        ]);

        return back()->with('status', 'projektfilter-created')->with('newSetId', $set->id);
    }

    public function activate(Request $request, ProjectFilterSet $projectFilterSet): RedirectResponse
    {
        $this->authorizeOwnership($request, $projectFilterSet);

        ProjectFilterSet::where('user_id', $request->user()->id)->update(['is_active' => false]);
        $projectFilterSet->update(['is_active' => true]);

        return back()->with('status', 'projektfilter-activated');
    }

    public function destroy(Request $request, ProjectFilterSet $projectFilterSet): RedirectResponse
    {
        $this->authorizeOwnership($request, $projectFilterSet);

        $user = $request->user();

        if (ProjectFilterSet::where('user_id', $user->id)->count() <= 1) {
            return back()->withErrors(['set' => __('Das letzte Filterset kann nicht gelöscht werden.')]);
        }

        $wasActive = $projectFilterSet->is_active;
        $projectFilterSet->delete();

        if ($wasActive) {
            ProjectFilterSet::where('user_id', $user->id)->oldest()->first()?->update(['is_active' => true]);
        }

        return back()->with('status', 'projektfilter-deleted');
    }

    /**
     * @return array{filter_fields: list<string>, filter_values: array<string, mixed>}
     */
    private function configFromRequest(Request $request): array
    {
        return [
            'filter_fields' => array_values($request->input('filter_fields', [])),
            'filter_values' => ProjectFilterCatalog::filtersFromRequest($request),
        ];
    }

    private function ownedSet(Request $request, int $setId): ProjectFilterSet
    {
        $set = ProjectFilterSet::findOrFail($setId);
        $this->authorizeOwnership($request, $set);

        return $set;
    }

    private function authorizeOwnership(Request $request, ProjectFilterSet $set): void
    {
        abort_if($set->user_id !== $request->user()->id, 403);
    }
}
