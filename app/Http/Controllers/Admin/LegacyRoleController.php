<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Rolle"-Verwaltung (LegacyRole, fachliche Funktion wie TR/PM-PT, siehe
 * Model-Docblock) als "klitzekleines" Unterbereich-Overlay aus dem
 * Personen-Overlay heraus - gleiches Muster wie CompanyController.
 */
class LegacyRoleController extends Controller
{
    public function index(Request $request): View
    {
        $legacyRoles = LegacyRole::query()->where('tenant_id', $request->user()->tenant_id)
            ->withCount('people')->orderBy('name')->get();

        return view('admin.legacy-roles.partials.manage-body', ['legacyRoles' => $legacyRoles]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        LegacyRole::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $name,
        ]);

        return redirect()->route('admin.legacy-roles');
    }

    public function update(Request $request, LegacyRole $legacyRole): RedirectResponse
    {
        abort_unless($legacyRole->tenant_id === $request->user()->tenant_id, 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $legacyRole->update(['name' => $name]);

        return redirect()->route('admin.legacy-roles');
    }

    public function destroy(Request $request, LegacyRole $legacyRole): RedirectResponse
    {
        abort_unless($legacyRole->tenant_id === $request->user()->tenant_id, 404);

        if ($legacyRole->people()->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? LegacyRole::query()->where('tenant_id', $legacyRole->tenant_id)->where('id', '!=', $legacyRole->id)->find($request->integer('reassign_to'))
                : null;
            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);
            $legacyRole->people()->update(['legacy_role_id' => $reassignTo?->id]);
        }

        $legacyRole->delete();

        return redirect()->route('admin.legacy-roles');
    }
}
