<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\Permission;
use App\Models\Person;
use App\Models\RolePermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verwaltung des Rechtekonzepts (Ebene 3) - Nav-Ebene 2 unter "Admin".
 * Ein Editor, analog Viettos rechte.php: links wählt man WEN (Funktions-
 * gruppe oder Person), rechts hakt man an WAS. Bewusst nicht als Matrix
 * (Rechte x Gruppen nebeneinander) - das würde mit jedem neuen Recht
 * breiter, hier wächst nur die rechte Liste nach unten.
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = $request->user()->tenant_id;

        $functionGroups = FunctionGroup::query()->where('tenant_id', $tenantId)->orderBy('sort')->get();
        $people = Person::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'active']);

        // Vereinte Liste (siehe Konzept-Absprache: Navigation ist nur ein
        // normales Recht im selben Katalog, kein eigenes System) - solange
        // der Katalog klein ist, ohne weitere Kategorisierung; die wird
        // fällig, sobald hier deutlich mehr Rechte stehen.
        $permissions = Permission::query()->orderBy('key')->get();

        $selectedGroup = null;
        $selectedPerson = null;
        $grantedPermissionIds = collect();
        $personOverrides = collect();
        $showAdminSection = false;
        $adminPermissionIds = collect();

        if ($request->boolean('admin') && $request->user()->role === 'super_admin') {
            // Die Rechte-Vorlage der Admin-Rolle verwaltet ausschließlich der
            // Super-Admin - ein normaler Admin bekommt diesen Bereich gar
            // nicht erst zu sehen (siehe access-admin-Gate im View) und soll
            // auch nicht erfahren, dass es über ihm noch eine Ebene gibt.
            // Super-Admin hat per Definition immer alle Rechte (siehe
            // Gate::before() in AppServiceProvider) - die Liste wird der
            // Vollständigkeit halber nur informativ mitangezeigt, nicht
            // bearbeitbar.
            $showAdminSection = true;
            $adminPermissionIds = RolePermission::query()
                ->where('tenant_id', $tenantId)
                ->where('role', 'admin')
                ->pluck('permission_id');
        } elseif ($request->filled('group')) {
            $selectedGroup = $functionGroups->firstWhere('id', (int) $request->query('group'));
            if ($selectedGroup) {
                $grantedPermissionIds = $selectedGroup->permissions()->pluck('permissions.id');
            }
        } elseif ($request->filled('person')) {
            $selectedPerson = Person::query()->where('tenant_id', $tenantId)->find($request->query('person'));
            if ($selectedPerson) {
                $personOverrides = $selectedPerson->permissionOverrides()->get()->keyBy('id');
            }
        }

        return view('admin.rechte.index', [
            'functionGroups' => $functionGroups,
            'people' => $people,
            'permissions' => $permissions,
            'selectedGroup' => $selectedGroup,
            'selectedPerson' => $selectedPerson,
            'grantedPermissionIds' => $grantedPermissionIds,
            'personOverrides' => $personOverrides,
            'showAdminSection' => $showAdminSection,
            'adminPermissionIds' => $adminPermissionIds,
        ]);
    }

    /**
     * Rechte-Vorlage EINER Funktionsgruppe komplett neu setzen.
     */
    public function updateFunctionGroup(Request $request, FunctionGroup $group): RedirectResponse
    {
        abort_unless($group->tenant_id === $request->user()->tenant_id, 404);

        $permissionIds = collect($request->array('permissions'))
            ->mapWithKeys(fn ($permissionId) => [$permissionId => ['tenant_id' => $group->tenant_id]]);
        $group->permissions()->sync($permissionIds);

        return redirect()->route('admin.rechte', ['group' => $group->id])->with('status', 'rechte-updated');
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

        return redirect()->route('admin.rechte', ['person' => $person->id])->with('status', 'rechte-updated');
    }

    /**
     * Rechte-Vorlage der Admin-Rolle komplett neu setzen - nur für
     * Super-Admin aufrufbar, siehe index(). Wer Admin IST, wird nicht hier,
     * sondern (künftig) in der Personenverwaltung festgelegt.
     */
    public function updateAdminPermissions(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'super_admin', 403);

        $tenantId = $request->user()->tenant_id;
        $permissionIds = collect($request->array('permissions'))->map(fn ($id) => (int) $id);

        RolePermission::query()->where('tenant_id', $tenantId)->where('role', 'admin')->delete();

        $permissionIds->each(fn ($permissionId) => RolePermission::query()->create([
            'tenant_id' => $tenantId,
            'role' => 'admin',
            'permission_id' => $permissionId,
        ]));

        return redirect()->route('admin.rechte', ['admin' => 1])->with('status', 'rechte-updated');
    }
}
