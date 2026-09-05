<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\Permission;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verwaltung des Rechtekonzepts (Ebene 3) - Nav-Ebene 2 unter "Admin".
 * Zwei Bausteine, siehe Person::hasPermission(): die Funktionsgruppen-
 * Rechte-Vorlage (Matrix) und individuelle Personen-Ausnahmen (Zusatz/
 * Entzug on top der Vorlage).
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $functionGroups = FunctionGroup::query()->where('tenant_id', $tenantId)->orderBy('sort')->with('permissions:id')->get();
        $permissions = Permission::query()->orderBy('key')->get();

        $selectedPerson = null;
        $personOverrides = collect();
        if ($request->filled('person')) {
            $selectedPerson = Person::query()->where('tenant_id', $tenantId)->find($request->query('person'));
            if ($selectedPerson) {
                $personOverrides = $selectedPerson->permissionOverrides()->get()->keyBy('id');
            }
        }

        $people = Person::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'active']);

        return view('admin.rechte.index', [
            'functionGroups' => $functionGroups,
            'permissions' => $permissions,
            'people' => $people,
            'selectedPerson' => $selectedPerson,
            'personOverrides' => $personOverrides,
        ]);
    }

    /**
     * Rechte-Vorlage je Funktionsgruppe komplett aus der Matrix neu setzen.
     * Checkbox-Grid: permissions[{function_group_id}][] = {permission_id}.
     */
    public function updateFunctionGroups(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $submitted = $request->array('permissions');

        FunctionGroup::query()->where('tenant_id', $tenantId)->get()->each(function (FunctionGroup $group) use ($submitted) {
            $permissionIds = collect($submitted[$group->id] ?? [])
                ->mapWithKeys(fn ($permissionId) => [$permissionId => ['tenant_id' => $group->tenant_id]]);
            $group->permissions()->sync($permissionIds);
        });

        return back()->with('status', 'rechte-updated');
    }

    /**
     * Individuelle Ausnahmen einer Person setzen - je Recht entweder keine
     * Ausnahme (Vorlage gilt), Zusatz (granted=1) oder Entzug (granted=0).
     * override[{permission_id}] = ''|'1'|'0'.
     */
    public function updatePerson(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);

        $overrides = $request->array('override');

        foreach (Permission::query()->pluck('id') as $permissionId) {
            $value = $overrides[$permissionId] ?? '';

            if ($value === '') {
                $person->permissionOverrides()->detach($permissionId);
            } else {
                $person->permissionOverrides()->syncWithoutDetaching([
                    $permissionId => ['granted' => $value === '1', 'tenant_id' => $person->tenant_id],
                ]);
            }
        }

        return redirect()->route('admin.rechte', ['person' => $person->id]);
    }
}
