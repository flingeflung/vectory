<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyRole;
use App\Models\Tenant;
use App\Support\CurrentTenant;
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
        $tenantId = $this->targetTenantId($request);

        $legacyRoles = LegacyRole::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->withCount('people')->orderBy('name')->get();

        return view('admin.legacy-roles.partials.manage-body', ['legacyRoles' => $legacyRoles, 'tenantId' => $tenantId, 'tenantName' => Tenant::find($tenantId)?->name]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        LegacyRole::query()->create([
            'tenant_id' => $this->targetTenantId($request),
            'name' => $name,
        ]);

        return redirect()->route('admin.legacy-roles');
    }

    public function update(Request $request, int $legacyRole): RedirectResponse
    {
        $legacyRole = LegacyRole::query()->withoutGlobalScope('tenant')->findOrFail($legacyRole);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $legacyRole->tenant_id), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $legacyRole->update(['name' => $name]);

        return redirect()->route('admin.legacy-roles');
    }

    public function destroy(Request $request, int $legacyRole): RedirectResponse
    {
        $legacyRole = LegacyRole::query()->withoutGlobalScope('tenant')->findOrFail($legacyRole);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $legacyRole->tenant_id), 404);

        if ($legacyRole->people()->withoutGlobalScope('tenant')->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? LegacyRole::query()->withoutGlobalScope('tenant')->where('tenant_id', $legacyRole->tenant_id)->where('id', '!=', $legacyRole->id)->find($request->integer('reassign_to'))
                : null;
            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);
            $legacyRole->people()->withoutGlobalScope('tenant')->update(['legacy_role_id' => $reassignTo?->id]);
        }

        $legacyRole->delete();

        return redirect()->route('admin.legacy-roles');
    }

    /**
     * Katalog DER ANGEZEIGTEN PERSON (aus dem Personen-Overlay mitgegeben,
     * siehe layouts/app.blade.php) statt des aktiven Kunden, falls
     * mitgegeben und der Nutzer dafür berechtigt ist.
     */
    private function targetTenantId(Request $request): int
    {
        $requested = $request->integer('tenant_id') ?: null;

        if ($requested && CurrentTenant::canManageTenantCatalog($request->user(), $requested)) {
            return $requested;
        }

        return CurrentTenant::id();
    }
}
