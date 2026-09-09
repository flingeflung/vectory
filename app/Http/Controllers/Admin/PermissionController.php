<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verwaltung der Rechte-Sets ("Profile"-Muster, siehe Rechtekonzept-
 * Diskussion): links wählt man ein Set oder eine Person, rechts sieht/
 * bearbeitet man entweder die Rechte-Checkliste eines Sets (inkl. wer sie
 * gerade erbt) oder das Set einer Person. Funktionsgruppen und individuelle
 * Personen-Ausnahmen spielen hier bewusst keine Rolle mehr.
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();

        $templates = PermissionTemplate::query()->where('tenant_id', $tenantId)->orderBy('sort')->get();

        // withoutGlobalScope + visibleInTenant: zeigt auch DL-eigene
        // Mitarbeiter mit Kundenzugriff-Freigabe für diesen Kunden (gleiche
        // Lücke wie eben bei Funktionsgruppen, Ralf: "Bei den Rechten
        // stehen auch nur die beiden PM"). Das tatsächliche ZUWEISEN eines
        // Rechte-Sets bleibt trotzdem auf den Heimat-Mandanten beschränkt
        // (siehe assignPerson()) - das Set bestimmt die Rechte app-weit,
        // nicht nur für den gerade aktiven Kunden.
        $people = Person::query()
            ->withoutGlobalScope('tenant')
            ->visibleInTenant($tenantId)
            ->visibleToRole($request->user()->role)
            ->with([
                'department' => fn ($query) => $query->withoutGlobalScope('tenant'),
                // withoutGlobalScope noetig: bei DL-eigenen Mitarbeitern mit
                // Kundenzugriff gehoert das Set zu deren Heimat-Mandanten,
                // nicht zum gerade aktiven Kunden (siehe Kommentar oben).
                'permissionTemplate' => fn ($query) => $query->withoutGlobalScope('tenant'),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'active', 'permission_template_id', 'department_id']);

        // Katalog des aktiven Kunden PLUS Kataloge etwaiger freigegebener
        // Personen (Person::visibleTenantIds) - siehe FunctionGroupController
        // für dieselbe Begründung/denselben Bug-Report (leeres Dropdown bei
        // einem frischen Kunden ohne Personen, obwohl der Katalog existiert).
        $departments = Department::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', Person::visibleTenantIds($tenantId))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name');

        $permissions = Permission::query()->orderBy('key')->get();

        $selectedTemplate = null;
        $selectedPerson = null;
        $grantedPermissionIds = collect();
        $templatePeople = collect();

        if ($request->filled('set')) {
            $selectedTemplate = $templates->firstWhere('id', (int) $request->query('set'));
            if ($selectedTemplate) {
                $grantedPermissionIds = $selectedTemplate->permissions()->pluck('permissions.id');
                $templatePeople = $people->where('permission_template_id', $selectedTemplate->id)->values();

                // Bei der Bulk-Zuordnung erst die schon angehakten Personen
                // (alphabetisch), danach der Rest (ebenfalls alphabetisch) -
                // sortByDesc ist stabil, behält also die alphabetische
                // Reihenfolge innerhalb jeder der beiden Gruppen bei.
                $people = $people->sortByDesc(fn (Person $person) => $person->permission_template_id === $selectedTemplate->id)->values();
            }
        } elseif ($request->filled('person')) {
            $selectedPerson = Person::query()->withoutGlobalScope('tenant')->visibleInTenant($tenantId)
                ->with(['department' => fn ($query) => $query->withoutGlobalScope('tenant')])
                ->find($request->query('person'));
        }

        return view('admin.rechte.index', [
            'templates' => $templates,
            'people' => $people,
            'departments' => $departments,
            'permissions' => $permissions,
            'selectedTemplate' => $selectedTemplate,
            'selectedPerson' => $selectedPerson,
            // Das Rechte-Set einer per Kundenzugriff sichtbaren Person kann
            // hier nur ANGEZEIGT, nicht geändert werden - es bestimmt ihre
            // Rechte app-weit, nicht nur für diesen Kunden (siehe
            // assignPerson()).
            'selectedPersonIsHomeTenant' => $selectedPerson?->tenant_id === $tenantId,
            'grantedPermissionIds' => $grantedPermissionIds,
            'templatePeople' => $templatePeople,
        ]);
    }

    /**
     * Neues Set anlegen: immer als Kopie eines vorhandenen (übernimmt
     * dessen Rechte 1:1 als Startpunkt) - "auf Basis von" X, Name vergeben.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $base = PermissionTemplate::query()->where('tenant_id', $tenantId)->findOrFail($request->integer('base_id'));

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $nextSort = 1 + (int) PermissionTemplate::query()->where('tenant_id', $tenantId)->max('sort');
        $template = PermissionTemplate::query()->create([
            'tenant_id' => $tenantId,
            'role' => $base->role,
            'name' => $name,
            'sort' => $nextSort,
        ]);
        $template->permissions()->sync($base->permissions()->pluck('permissions.id'));

        return redirect()->route('admin.rechte', ['set' => $template->id])->with('status', 'rechte-updated');
    }

    /**
     * Rechte EINES Sets komplett neu setzen - wirkt sofort für alle
     * Personen, die dieses Set haben (keine Rückfrage nötig, siehe
     * "Personen mit diesem Set" im View - macht die Tragweite sichtbar).
     */
    public function update(Request $request, PermissionTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $permissionIds = collect($request->array('permissions'))->map(fn ($id) => (int) $id);
        $template->update(['name' => $name]);
        $template->permissions()->sync($permissionIds);

        return redirect()->route('admin.rechte', ['set' => $template->id])->with('status', 'rechte-updated');
    }

    /**
     * Set löschen - hat es noch zugeordnete Personen, muss vorher ein
     * Ziel-Set gewählt werden (nie Personen ohne Set zurücklassen).
     */
    public function destroy(Request $request, PermissionTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        if ($template->people()->exists()) {
            $reassignTo = PermissionTemplate::query()
                ->where('tenant_id', $template->tenant_id)
                ->where('id', '!=', $template->id)
                ->find($request->integer('reassign_to'));

            abort_if($reassignTo === null, 422);

            $template->people->each(fn (Person $person) => $this->assignTemplate($person, $reassignTo));
        }

        $template->delete();

        return redirect()->route('admin.rechte')->with('status', 'rechte-updated');
    }

    /**
     * Einer Person ihr Set zuweisen - bestimmt darüber vollständig ihre
     * Rechte, keine individuellen Ausnahmen mehr.
     */
    public function assignPerson(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === CurrentTenant::id(), 404);

        $template = PermissionTemplate::query()->where('tenant_id', $person->tenant_id)->findOrFail($request->integer('permission_template_id'));

        $this->assignTemplate($person, $template);

        return redirect()->route('admin.rechte', ['person' => $person->id])->with('status', 'rechte-updated');
    }

    /**
     * Mehrere bisher unzugeordnete Personen auf einmal diesem Set zuweisen -
     * Massenwerkzeug fürs Ersteinrichten (siehe View: nur wirklich
     * unzugeordnete Personen sind dort überhaupt anklickbar). Wer schon ein
     * Set hat, wird hier serverseitig ignoriert statt verschoben - Wechsel
     * eines bestehenden Sets läuft bewusst nur über assignPerson() oben.
     */
    public function assignPeopleToTemplate(Request $request, PermissionTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $personIds = collect($request->array('person_ids'))->map(fn ($id) => (int) $id);

        Person::query()
            ->where('tenant_id', $template->tenant_id)
            ->whereIn('id', $personIds)
            ->whereNull('permission_template_id')
            ->get()
            ->each(fn (Person $person) => $this->assignTemplate($person, $template));

        return redirect()->route('admin.rechte', ['set' => $template->id])->with('status', 'rechte-updated');
    }

    /**
     * Reine visuelle Rangordnung der Sets per Drag&Drop (z.B. um sie
     * absteigend nach Rechte-Umfang anzuordnen) - hat keinerlei fachlichen
     * Effekt, nur die Reihenfolge in der Liste.
     */
    public function reorderSets(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();

        collect($request->array('sets'))->values()->each(function (string $id, int $index) use ($tenantId) {
            PermissionTemplate::query()->where('tenant_id', $tenantId)->where('id', (int) $id)->update(['sort' => $index]);
        });

        return redirect()->route('admin.rechte');
    }

    /**
     * Wer Admin wird, entscheidet jeder Admin innerhalb des eigenen
     * Mandanten selbst - kein Super-Admin-Vorbehalt mehr (Ralf: als DL-
     * Admin will er nicht bei jeder Rechtevergabe an eigenes Personal den
     * Super-Admin fragen müssen, und wer bei ihm Admin-Rechte hat, geht
     * den Software-Anbieter nichts an). Die Route selbst bleibt ohnehin
     * hinter access-admin - nur echte Admins/Super-Admins kommen überhaupt
     * hierher.
     */
    private function assignTemplate(Person $person, PermissionTemplate $template): void
    {
        $person->update(['permission_template_id' => $template->id]);

        if ($person->user) {
            $person->user->update(['role' => $template->role]);
        }
    }
}
