<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Firmen verwalten - kleines Overlay, aus der Personenverwaltung heraus
 * per Link neben dem "Firma"-Feld öffenbar (Schritt 1 des von Ralf
 * angekündigten Musters, das später auf Abteilung/Geschäftsbereich/weitere
 * "klitzekleine Unterbereiche" übertragen wird - bewusst noch nicht
 * generisch abstrahiert, bis das Muster ein zweites Mal gebraucht wird).
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->targetTenantId($request);

        $companies = Company::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->withCount('people')
            ->orderBy('name')
            ->get();

        return view('admin.companies.partials.manage-body', ['companies' => $companies, 'tenantId' => $tenantId, 'tenantName' => Tenant::find($tenantId)?->name]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Company::query()->create([
            'tenant_id' => $this->targetTenantId($request),
            'name' => $name,
            'short_name' => trim((string) $request->string('short_name')) ?: mb_substr($name, 0, 20),
        ]);

        return redirect()->route('admin.companies');
    }

    public function update(Request $request, int $company): RedirectResponse
    {
        $company = Company::query()->withoutGlobalScope('tenant')->findOrFail($company);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $company->tenant_id), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $company->update([
            'name' => $name,
            'short_name' => trim((string) $request->string('short_name')) ?: mb_substr($name, 0, 20),
        ]);

        return redirect()->route('admin.companies');
    }

    /**
     * Löschen - hat die Firma noch zugeordnete Personen, muss vorher ein
     * Ziel gewählt werden (eine andere Firma oder "nicht zugewiesen"),
     * gleiches Prinzip wie beim Löschen eines Rechte-Sets.
     */
    public function destroy(Request $request, int $company): RedirectResponse
    {
        $company = Company::query()->withoutGlobalScope('tenant')->findOrFail($company);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $company->tenant_id), 404);

        if ($company->people()->withoutGlobalScope('tenant')->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? Company::query()->withoutGlobalScope('tenant')->where('tenant_id', $company->tenant_id)->where('id', '!=', $company->id)->find($request->integer('reassign_to'))
                : null;

            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);

            $company->people()->withoutGlobalScope('tenant')->update(['company_id' => $reassignTo?->id]);
        }

        $company->delete();

        return redirect()->route('admin.companies');
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
