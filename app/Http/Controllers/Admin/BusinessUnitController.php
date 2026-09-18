<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Geschäftsbereichs-Verwaltung als "klitzekleines" Unterbereich-Overlay aus
 * dem Personen-Overlay heraus - gleiches Muster wie CompanyController. Die
 * frühere eigenständige Seite (admin/business-units) ist damit obsolet.
 */
class BusinessUnitController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->targetTenantId($request);

        $businessUnits = BusinessUnit::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->withCount('people')
            ->orderBy('name')
            ->get();

        return view('admin.business-units.partials.manage-body', ['businessUnits' => $businessUnits, 'tenantId' => $tenantId, 'tenantName' => Tenant::find($tenantId)?->name]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $this->targetTenantId($request);
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        BusinessUnit::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
        ]);

        return redirect()->route('admin.geschaeftsbereiche');
    }

    public function update(Request $request, int $businessUnit): RedirectResponse
    {
        $businessUnit = BusinessUnit::query()->withoutGlobalScope('tenant')->findOrFail($businessUnit);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $businessUnit->tenant_id), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $businessUnit->update(['name' => $name, 'active' => $request->boolean('active')]);

        return redirect()->route('admin.geschaeftsbereiche');
    }

    public function destroy(Request $request, int $businessUnit): RedirectResponse
    {
        $businessUnit = BusinessUnit::query()->withoutGlobalScope('tenant')->findOrFail($businessUnit);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $businessUnit->tenant_id), 404);

        if ($businessUnit->people()->withoutGlobalScope('tenant')->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? BusinessUnit::query()->withoutGlobalScope('tenant')->where('tenant_id', $businessUnit->tenant_id)->where('id', '!=', $businessUnit->id)->find($request->integer('reassign_to'))
                : null;
            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);
            $businessUnit->people()->withoutGlobalScope('tenant')->update(['business_unit_id' => $reassignTo?->id]);
        }

        $businessUnit->delete();

        return redirect()->route('admin.geschaeftsbereiche');
    }

    /**
     * Katalog DER ANGEZEIGTEN PERSON (aus dem Personen-Overlay mitgegeben,
     * siehe layouts/app.blade.php) statt des aktiven Kunden, falls
     * mitgegeben und der Nutzer dafür berechtigt ist - sonst wie bisher der
     * aktive Kunde (z.B. beim Neuanlegen ohne Personen-Kontext).
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
