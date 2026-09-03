<?php

namespace App\Http\Controllers;

use App\Models\DisplayFilterSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisplayFilterController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['short_length.*' => ['nullable', 'integer', 'min:1', 'max:2000']]);

        $set = $this->ownedSet($request, (int) $request->input('set_id'));

        $set->update(['config' => ['columns' => $this->columnsFromRequest($request)]]);

        return back()->with('status', 'display-filter-saved');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_length.*' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        $user = $request->user();

        DisplayFilterSet::where('user_id', $user->id)->update(['is_active' => false]);

        $set = DisplayFilterSet::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'config' => ['columns' => $this->columnsFromRequest($request)],
            'is_active' => true,
        ]);

        return back()->with('status', 'display-filter-created')->with('newSetId', $set->id);
    }

    public function activate(Request $request, DisplayFilterSet $displayFilterSet): RedirectResponse
    {
        $this->authorizeOwnership($request, $displayFilterSet);

        DisplayFilterSet::where('user_id', $request->user()->id)->update(['is_active' => false]);
        $displayFilterSet->update(['is_active' => true]);

        return back()->with('status', 'display-filter-activated');
    }

    public function destroy(Request $request, DisplayFilterSet $displayFilterSet): RedirectResponse
    {
        $this->authorizeOwnership($request, $displayFilterSet);

        $user = $request->user();

        if (DisplayFilterSet::where('user_id', $user->id)->count() <= 1) {
            return back()->withErrors(['set' => __('Das letzte Filterset kann nicht gelöscht werden.')]);
        }

        $wasActive = $displayFilterSet->is_active;
        $displayFilterSet->delete();

        if ($wasActive) {
            DisplayFilterSet::where('user_id', $user->id)->oldest()->first()?->update(['is_active' => true]);
        }

        return back()->with('status', 'display-filter-deleted');
    }

    private function ownedSet(Request $request, int $setId): DisplayFilterSet
    {
        $set = DisplayFilterSet::findOrFail($setId);
        $this->authorizeOwnership($request, $set);

        return $set;
    }

    private function authorizeOwnership(Request $request, DisplayFilterSet $set): void
    {
        abort_if($set->user_id !== $request->user()->id, 403);
    }

    /**
     * @return list<array{key: string, visible: bool, long_text: bool, short_length: int|null}>
     */
    private function columnsFromRequest(Request $request): array
    {
        $order = $request->input('order', []);
        $visible = $request->input('visible', []);
        $longText = $request->input('long_text', []);
        $shortLength = $request->input('short_length', []);

        return array_map(
            fn (string $key) => [
                'key' => $key,
                'visible' => in_array($key, $visible, true),
                'long_text' => in_array($key, $longText, true),
                'short_length' => isset($shortLength[$key]) && $shortLength[$key] !== ''
                    ? (int) $shortLength[$key]
                    : null,
            ],
            $order
        );
    }
}
