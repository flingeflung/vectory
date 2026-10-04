<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlossaryTerm;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Begriffsverzeichnis pflegen (Ralf, 2026-10-04): Begriff -> Zielseite, nötiges Recht, Erklärsatz.
 * Nur Super-Admin (wie die Hilfeverwaltung).
 */
class GlossaryTermController extends Controller
{
    public function index(): View
    {
        $pages = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() && in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{'))
            ->map(fn ($route) => $route->getName())
            ->unique()->sort()->values();

        // Schlüssel => Bezeichnung (wie auf der Rechte-Seite), dazu die Zugriffsstufen
        $abilities = Permission::query()->orderBy('key')->pluck('label', 'key')->all()
            + [
                'access-admin' => __('Zugriffsstufe: mindestens Organisations-Admin'),
                'access-central-admin' => __('Zugriffsstufe: mindestens Zentral-Admin'),
                'access-superadmin' => __('Zugriffsstufe: Super-Admin'),
            ];

        return view('admin.begriffe.index', [
            'terms' => GlossaryTerm::query()->orderBy('term')->get(),
            'pages' => $pages,
            'abilities' => $abilities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        GlossaryTerm::query()->create($this->validated($request));
        GlossaryTerm::forgetCache();

        return redirect()->route('admin.begriffe')->with('status', 'saved');
    }

    public function update(Request $request, GlossaryTerm $glossaryTerm): RedirectResponse
    {
        $glossaryTerm->update($this->validated($request, $glossaryTerm));
        GlossaryTerm::forgetCache();

        return redirect()->route('admin.begriffe')->with('status', 'saved');
    }

    public function destroy(GlossaryTerm $glossaryTerm): RedirectResponse
    {
        $glossaryTerm->delete();
        GlossaryTerm::forgetCache();

        return redirect()->route('admin.begriffe')->with('status', 'deleted');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?GlossaryTerm $current = null): array
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:100', Rule::unique('glossary_terms', 'term')->ignore($current?->id)],
            'route_name' => ['required', 'string', 'max:150'],
            'ability' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'term.unique' => __('Diesen Begriff gibt es schon.'),
        ]);
        $data['term'] = trim($data['term']);
        $data['ability'] = ($data['ability'] ?? '') !== '' ? $data['ability'] : null;

        return $data;
    }
}
