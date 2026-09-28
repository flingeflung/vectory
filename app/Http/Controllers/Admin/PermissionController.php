<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\Tenant;
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

        // Ralf, 2026-09-28: zwei getrennte Listen (Sets/Bausteine, siehe
        // PermissionTemplate-Model-Docblock) - $templates bleibt bewusst
        // "nur Sets", damit bestehende Stellen (Personen-Zuweisung,
        // Klon-Basis für ein neues Set) unverändert nur echte Sets sehen.
        $templates = PermissionTemplate::query()->where('tenant_id', $tenantId)->where('is_baustein', false)->orderBy('sort')->get();
        $bausteine = PermissionTemplate::query()->where('tenant_id', $tenantId)->where('is_baustein', true)->orderBy('sort')->get();

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
        $embeddedBausteinIds = collect();
        $usedInSets = collect();
        $derivedSets = collect();

        if ($request->filled('set')) {
            // Der Query-Param heißt weiterhin "set", zeigt aber auf EINEN von
            // beiden (Set ODER Baustein, siehe Model-Docblock) - deshalb
            // direkt aus der DB statt aus der (jetzt nach Typ getrennten)
            // $templates-Collection gesucht.
            $selectedTemplate = PermissionTemplate::query()->where('tenant_id', $tenantId)
                ->with(['bausteine', 'usedInSets', 'basis'])
                ->find((int) $request->query('set'));
            if ($selectedTemplate) {
                $grantedPermissionIds = $selectedTemplate->permissions()->pluck('permissions.id');
                if ($selectedTemplate->is_baustein) {
                    $usedInSets = $selectedTemplate->usedInSets;
                } else {
                    $templatePeople = $people->where('permission_template_id', $selectedTemplate->id)->values();
                    $embeddedBausteinIds = $selectedTemplate->bausteine->pluck('id');
                    $derivedSets = $selectedTemplate->derivedSets;

                    // Bei der Bulk-Zuordnung erst die schon angehakten Personen
                    // (alphabetisch), danach der Rest (ebenfalls alphabetisch) -
                    // sortByDesc ist stabil, behält also die alphabetische
                    // Reihenfolge innerhalb jeder der beiden Gruppen bei.
                    $people = $people->sortByDesc(fn (Person $person) => $person->permission_template_id === $selectedTemplate->id)->values();
                }
            }
        } elseif ($request->filled('person')) {
            $selectedPerson = Person::query()->withoutGlobalScope('tenant')->visibleInTenant($tenantId)
                ->with(['department' => fn ($query) => $query->withoutGlobalScope('tenant')])
                ->find($request->query('person'));
        }

        return view('admin.rechte.index', [
            'templates' => $templates,
            'bausteine' => $bausteine,
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
            'selectedPersonHomeTenantName' => $selectedPerson && $selectedPerson->tenant_id !== $tenantId
                ? Tenant::query()->find($selectedPerson->tenant_id)?->name
                : null,
            'grantedPermissionIds' => $grantedPermissionIds,
            'templatePeople' => $templatePeople,
            'embeddedBausteinIds' => $embeddedBausteinIds,
            'usedInSets' => $usedInSets,
            'derivedSets' => $derivedSets,
        ]);
    }

    /**
     * Neues Set ODER neuen Baustein anlegen (siehe Model-Docblock) - wahl-
     * weise leer oder als Kopie eines vorhandenen DESSELBEN Typs (übernimmt
     * dessen Rechte 1:1 als Startpunkt, bei einem Set zusätzlich dessen
     * eingebundene Bausteine). base_id ist bewusst optional, nicht
     * required: der allererste Baustein eines Mandanten hat naturgemäß noch
     * keine Vorlage zum Klonen (anders als Sets, die immer vorbestückt
     * sind, siehe Standard-Seed). is_baustein kommt aus dem jeweiligen
     * "+ Neu"-Formular (eigenes je Box in der View), nicht frei wählbar -
     * so kann eine Kopie nie den Typ wechseln.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $isBaustein = $request->boolean('is_baustein');
        $base = $request->filled('base_id')
            ? PermissionTemplate::query()->where('tenant_id', $tenantId)->where('is_baustein', $isBaustein)->findOrFail($request->integer('base_id'))
            : null;

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $nextSort = 1 + (int) PermissionTemplate::query()->where('tenant_id', $tenantId)->max('sort');
        $template = PermissionTemplate::query()->create([
            'tenant_id' => $tenantId,
            'role' => $base?->role ?? 'user',
            'name' => $name,
            'sort' => $nextSort,
            'is_baustein' => $isBaustein,
        ]);
        if ($base) {
            $template->permissions()->sync($base->permissions()->pluck('permissions.id'));
            if (! $isBaustein) {
                $template->bausteine()->sync($base->bausteine()->pluck('permission_templates.id'));
            }
        }

        return redirect()->route('admin.rechte', ['set' => $template->id])->with('status', 'rechte-updated');
    }

    /**
     * Rechte EINES Sets/Bausteins komplett neu setzen - wirkt sofort für
     * alle Personen, die dieses Set haben bzw. (indirekt) für alle Sets, die
     * diesen Baustein einbinden (keine Rückfrage nötig, siehe "Personen mit
     * diesem Set"/"Eingebunden in diese Sets" im View - macht die Tragweite
     * sichtbar). bausteine[] wird bewusst NUR bei einem Set übernommen - ein
     * Baustein bindet nie weitere Bausteine ein (siehe Model-Docblock),
     * unabhängig davon, was ein manipulierter Request hier mitschickt.
     */
    public function update(Request $request, PermissionTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $permissionIds = collect($request->array('permissions'))->map(fn ($id) => (int) $id);
        $template->update(['name' => $name]);
        $template->permissions()->sync($permissionIds);

        if (! $template->is_baustein) {
            $bausteinIds = collect($request->array('bausteine'))->map(fn ($id) => (int) $id);
            $validBausteinIds = PermissionTemplate::query()->where('tenant_id', $template->tenant_id)
                ->where('is_baustein', true)->whereIn('id', $bausteinIds)->pluck('id');
            $template->bausteine()->sync($validBausteinIds);

            // Ralf, 2026-09-28: "Basis" - lebende statt kopierte Vererbung
            // (siehe Model-Docblock). Nur bei einem Set relevant, nie bei
            // einem Baustein. Leer = keine Basis (Kette gekappt).
            $basisId = $request->filled('basis_id') ? $request->integer('basis_id') : null;
            if ($basisId !== null) {
                $basis = PermissionTemplate::query()->where('tenant_id', $template->tenant_id)
                    ->where('is_baustein', false)->where('id', '!=', $template->id)->find($basisId);
                abort_if($basis === null, 422);
                abort_if($this->wouldCreateCycle($basis, $template->id), 422, __('Das würde einen Ringbezug erzeugen.'));
                $template->update(['basis_id' => $basis->id]);
            } else {
                $template->update(['basis_id' => null]);
            }
        }

        return redirect()->route('admin.rechte', ['set' => $template->id])->with('status', 'rechte-updated');
    }

    /**
     * Würde $candidateBasis als Basis von Set $targetId zu einem Ringbezug
     * führen? Läuft die Basis-Kette von $candidateBasis aus rückwärts hoch
     * und prüft, ob $targetId darin vorkommt (dann würde die Kette
     * irgendwann wieder bei sich selbst ankommen).
     */
    private function wouldCreateCycle(PermissionTemplate $candidateBasis, int $targetId): bool
    {
        $current = $candidateBasis;
        while ($current !== null) {
            if ($current->id === $targetId) {
                return true;
            }
            $current = $current->basis;
        }

        return false;
    }

    /**
     * Set löschen - hat es noch zugeordnete Personen, muss vorher ein
     * Ziel-Set gewählt werden (nie Personen ohne Set zurücklassen). Dient
     * dieses Set noch als lebende Basis für andere Sets, wird das Löschen
     * ganz blockiert (kein Reassign-Mechanismus dafür - Ralf muss die
     * Basis-Referenz dort erst bewusst ändern/entfernen, sonst würden
     * andere Sets stillschweigend Rechte verlieren).
     */
    public function destroy(Request $request, PermissionTemplate $template): RedirectResponse
    {
        abort_unless($template->tenant_id === CurrentTenant::id(), 404);
        abort_if($template->derivedSets()->exists(), 422);

        if ($template->people()->exists()) {
            $reassignTo = PermissionTemplate::query()
                ->where('tenant_id', $template->tenant_id)
                ->where('is_baustein', false)
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

        // Ein Baustein ist nie einer Person zuweisbar (siehe Model-Docblock) -
        // is_baustein=false hier statt nur im View zu verlassen.
        $template = PermissionTemplate::query()->where('tenant_id', $person->tenant_id)
            ->where('is_baustein', false)->findOrFail($request->integer('permission_template_id'));

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
        abort_if($template->is_baustein, 422);

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
            $person->user->update(['role' => $template->effectiveRole()]);
        }
    }
}
