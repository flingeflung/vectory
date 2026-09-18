<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Abteilungs-Verwaltung als "klitzekleines" Unterbereich-Overlay aus dem
 * Personen-Overlay heraus - gleiches Muster wie CompanyController.
 */
class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $this->targetTenantId($request);

        $departments = Department::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->withCount('people')->orderBy('name')->get();

        return view('admin.departments.partials.manage-body', ['departments' => $departments, 'tenantId' => $tenantId, 'tenantName' => Tenant::find($tenantId)?->name]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        Department::query()->create([
            'tenant_id' => $this->targetTenantId($request),
            'name' => $name,
            'short_name' => $this->shortNameFromRequest($request),
        ]);

        return redirect()->route('admin.departments');
    }

    public function update(Request $request, int $department): RedirectResponse
    {
        $department = Department::query()->withoutGlobalScope('tenant')->findOrFail($department);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $department->tenant_id), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $department->update([
            'name' => $name,
            'short_name' => $this->shortNameFromRequest($request),
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('admin.departments');
    }

    private function shortNameFromRequest(Request $request): ?string
    {
        $shortName = mb_substr(trim((string) $request->string('short_name')), 0, 10);

        return $shortName === '' ? null : $shortName;
    }

    public function destroy(Request $request, int $department): RedirectResponse
    {
        $department = Department::query()->withoutGlobalScope('tenant')->findOrFail($department);
        abort_unless(CurrentTenant::canManageTenantCatalog($request->user(), $department->tenant_id), 404);

        if ($department->people()->withoutGlobalScope('tenant')->exists()) {
            $reassignTo = $request->filled('reassign_to')
                ? Department::query()->withoutGlobalScope('tenant')->where('tenant_id', $department->tenant_id)->where('id', '!=', $department->id)->find($request->integer('reassign_to'))
                : null;
            abort_if($request->filled('reassign_to') && $reassignTo === null, 422);
            $department->people()->withoutGlobalScope('tenant')->update(['department_id' => $reassignTo?->id]);
        }

        $department->delete();

        return redirect()->route('admin.departments');
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
