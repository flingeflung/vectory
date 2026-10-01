<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Verwaltung der Funktionsgruppen (reines Task-Routing, siehe FunctionGroup-
 * Model-Docblock) - eigene, vollwertige Admin-Seite (nicht als "klitzekleine
 * Unterbereiche"-Overlay wie Firma/Abteilung/Geschäftsbereich/Rolle), weil
 * die Zuordnung direkte Prozessauswirkungen hat (Workflow-Routing,
 * Projektbeteiligte). Gleiches Grundmuster wie die Rechte-Verwaltung: links
 * Gruppen + Personen, rechts entweder die Mitglieder-Zuordnung einer Gruppe
 * oder die Gruppen-Zuordnung einer Person - nur als Mehrfachauswahl
 * (Checkbox statt Radio), da eine Person in mehreren Gruppen sein kann.
 */
class FunctionGroupController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();
        $catalogTenantId = FunctionGroup::catalogTenantId($tenantId);
        $canManageCatalog = ! SystemSetting::multiTenantEnabled() || $tenantId === $catalogTenantId;

        $groups = FunctionGroup::query()->availableForTenant($tenantId)->orderBy('name')->get();
        // withoutGlobalScope + visibleInTenant: zeigt auch DL-eigene
        // Mitarbeiter mit Kundenzugriff-Freigabe für diesen Kunden, nicht
        // nur dessen Heimat-Personen (Ralf: "Ich bin in der Maschinen AG.
        // Ich kann hier gar keine TR der Fktgrp zuweisen" - eigene TR-Leute
        // fehlten, weil ihr Heimat-Mandant ein anderer ist).
        $people = Person::query()->withoutGlobalScope('tenant')->visibleInTenant($tenantId)
            ->visibleToRole($request->user()->role)
            ->with([
                'department' => fn ($query) => $query->withoutGlobalScope('tenant'),
            ])
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'tenant_id', 'first_name', 'last_name', 'active', 'department_id']);

        // Katalog des aktiven Kunden PLUS Kataloge etwaiger freigegebener
        // Personen (Person::visibleTenantIds) - NICHT aus den gerade
        // angezeigten Personen abgeleitet, das war zu kurz gedacht: bei
        // einem frischen Kunden ohne Personen wäre die Liste dann leer,
        // obwohl der Katalog längst existiert (Ralfs Bug-Report: Abteilung
        // angelegt, Dropdown trotzdem leer, weil noch niemand zugeordnet
        // war).
        $departments = Department::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('tenant_id', Person::visibleTenantIds($tenantId))
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name');

        $selectedGroup = null;
        $selectedPerson = null;
        $groupMemberIds = collect();
        $personGroupIds = collect();
        $usage = null;
        $matrixUsage = collect();
        if ($canManageCatalog && SystemSetting::multiTenantEnabled()) {
            foreach (['function_group_member', 'workflow_step_function_group', 'project_people', 'project_workflow_step_people', 'project_template_function_group', 'project_function_group_hours', 'tasks'] as $table) {
                DB::table($table)->select('function_group_id', 'tenant_id')->distinct()->get()->each(function ($row) use ($matrixUsage) {
                    $matrixUsage->put($row->function_group_id.'.'.$row->tenant_id, true);
                });
            }
        }

        if ($request->filled('gruppe')) {
            $selectedGroup = $groups->firstWhere('id', (int) $request->query('gruppe'));
            if ($selectedGroup) {
                // withoutGlobalScope('tenant'): members() ist eine Relation
                // zu Person, würde sonst per Kundenzugriff freigegebene
                // Mitglieder (anderer Heimat-Mandant) rausfiltern - die
                // Checkbox hätte dann trotz gespeicherter Mitgliedschaft
                // "nicht angehakt" angezeigt (Ralfs Bug-Report: "speichert
                // nicht" - hat tatsächlich gespeichert, nur die Anzeige war
                // falsch).
                $groupMemberIds = $selectedGroup->members()->withoutGlobalScope('tenant')->pluck('people.id');
                $usage = $this->usageCounts($selectedGroup);

                // Mitglieder zuerst (alphabetisch), danach der Rest
                // (ebenfalls alphabetisch) - gleiches Muster wie bei der
                // Rechte-Set-Bulk-Zuordnung, sortByDesc ist stabil.
                $people = $people->sortByDesc(fn (Person $person) => $groupMemberIds->contains($person->id))->values();
            }
        } elseif ($request->filled('person')) {
            $selectedPerson = Person::query()->withoutGlobalScope('tenant')->visibleInTenant($tenantId)
                ->with(['department' => fn ($query) => $query->withoutGlobalScope('tenant')])
                ->find($request->query('person'));
            if ($selectedPerson) {
                // withoutGlobalScope('tenant'): bei einer per Kundenzugriff
                // sichtbaren Person gehören ihre Funktionsgruppen zu ihrem
                // Heimat-Mandanten, nicht zum aktiven - sonst zeigten die
                // Häkchen fälschlich "keine Gruppe zugeordnet" (gleicher
                // Fehler wie beim Rechte-Set, siehe PersonController).
                $personGroupIds = $selectedPerson->functionGroups()->withoutGlobalScope('tenant')->pluck('function_groups.id');
            }
        }

        return view('admin.function-groups.index', [
            'groups' => $groups,
            'people' => $people,
            'departments' => $departments,
            'selectedGroup' => $selectedGroup,
            'selectedPerson' => $selectedPerson,
            'groupMemberIds' => $groupMemberIds,
            'personGroupIds' => $personGroupIds,
            'usage' => $usage,
            'canManageCatalog' => $canManageCatalog,
            'matrixTenants' => $canManageCatalog && SystemSetting::multiTenantEnabled()
                ? Tenant::query()->whereKeyNot($catalogTenantId)->orderBy('name')->get()
                : collect(),
            'availability' => $canManageCatalog && SystemSetting::multiTenantEnabled()
                ? DB::table('function_group_tenant')->get()->groupBy('function_group_id')->map->pluck('tenant_id')
                : collect(),
            'matrixUsage' => $matrixUsage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $catalogTenantId = FunctionGroup::catalogTenantId($tenantId);
        abort_unless(! SystemSetting::multiTenantEnabled() || $tenantId === $catalogTenantId, 403);
        $name = trim((string) $request->string('name'));
        $shortName = trim((string) $request->string('short_name'));
        abort_if($name === '' || $shortName === '', 422);

        $group = FunctionGroup::query()->create([
            'tenant_id' => $catalogTenantId,
            'name' => $name,
            'short_name' => $shortName,
        ]);

        return redirect()->route('admin.function-groups', ['gruppe' => $group->id])->with('status', 'function-groups-updated');
    }

    public function update(Request $request, FunctionGroup $group): RedirectResponse
    {
        $this->authorizeCatalogChange($group);

        $name = trim((string) $request->string('name'));
        $shortName = trim((string) $request->string('short_name'));
        abort_if($name === '' || $shortName === '', 422);

        $isIllustrationGroup = $request->boolean('is_illustration_group');

        // Genau eine Illustrations-Gruppe pro Mandant - wird hier gesetzt,
        // fällt sie bei jeder anderen Gruppe des Mandanten automatisch weg
        // (GraphicOrderController/IllustrationOverviewController/Task
        // erwarten alle genau EIN Ergebnis, kein Mehrfach-Match).
        if ($isIllustrationGroup) {
            FunctionGroup::query()->where('tenant_id', $group->tenant_id)->where('id', '!=', $group->id)->update(['is_illustration_group' => false]);
        }

        $group->update([
            'name' => $name,
            'short_name' => $shortName,
            'active' => $request->boolean('active'),
            'is_illustration_group' => $isIllustrationGroup,
        ]);

        return redirect()->route('admin.function-groups', ['gruppe' => $group->id])->with('status', 'function-groups-updated');
    }

    /**
     * Löschen nur, wenn die Gruppe nirgends mehr in echten Projektdaten
     * verwendet wird (project_people, project_workflow_step_people,
     * workflow_step_function_group hängen alle per cascadeOnDelete daran -
     * ein Löschen würde also stillschweigend echte Projektbeteiligungen und
     * Workflow-Zuordnungen mitreißen). Das UI bietet den Löschen-Button in
     * diesem Fall gar nicht erst an (siehe view), dieser Check ist die
     * serverseitige Absicherung dagegen.
     */
    public function destroy(Request $request, FunctionGroup $group): RedirectResponse
    {
        $this->authorizeCatalogChange($group);

        $usage = $this->usageCounts($group);
        abort_if(array_sum($usage) > 0, 422);

        $group->delete();

        return redirect()->route('admin.function-groups');
    }

    public function updateMembers(Request $request, FunctionGroup $group): RedirectResponse
    {
        abort_unless($group->isAvailableForTenant(CurrentTenant::id()), 404);

        $personIds = collect($request->array('person_ids'))->map(fn ($id) => (int) $id);
        // withoutGlobalScope + visibleInTenant statt striktem tenant_id-
        // Vergleich: sonst würden per Kundenzugriff freigegebene DL-
        // Mitarbeiter beim Speichern stillschweigend wieder rausfallen
        // (angehakt, aber nicht übernommen) - gleicher Bug wie bei der
        // Personenliste vorher, hier nur noch nicht gefixt gewesen.
        $editablePeople = Person::query()->withoutGlobalScope('tenant')->where('tenant_id', CurrentTenant::id())->get(['id', 'tenant_id']);
        $validPeople = $editablePeople->whereIn('id', $personIds);
        $preservedPeople = $group->members()->withoutGlobalScope('tenant')->get(['people.id', 'people.tenant_id'])
            ->whereNotIn('id', $editablePeople->pluck('id'));

        // function_group_member.tenant_id ist NOT NULL ohne Default - sync()
        // füllt Pivot-Spalten sonst nicht automatisch, deshalb explizit je
        // Zeile mitgeben.
        $group->members()->sync($preservedPeople->concat($validPeople)->mapWithKeys(fn (Person $person) => [$person->id => ['tenant_id' => $person->tenant_id]]));

        return redirect()->route('admin.function-groups', ['gruppe' => $group->id])->with('status', 'function-groups-updated');
    }

    public function updatePersonGroups(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === CurrentTenant::id(), 404);

        $groupIds = collect($request->array('function_group_ids'))->map(fn ($id) => (int) $id);
        $validIds = FunctionGroup::query()->availableForTenant($person->tenant_id)->whereIn('id', $groupIds)->pluck('id');

        // function_group_member.tenant_id ist NOT NULL ohne Default - sync()
        // füllt Pivot-Spalten sonst nicht automatisch, deshalb explizit je
        // Zeile mitgeben.
        $person->functionGroups()->sync($validIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $person->tenant_id]]));

        return redirect()->route('admin.function-groups', ['person' => $person->id])->with('status', 'function-groups-updated');
    }

    public function updateAvailability(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $catalogTenantId = FunctionGroup::catalogTenantId($tenantId);
        abort_unless(SystemSetting::multiTenantEnabled() && $tenantId === $catalogTenantId, 403);

        $customerIds = Tenant::query()->whereKeyNot($catalogTenantId)->pluck('id');
        $availability = $request->array('availability');

        DB::transaction(function () use ($availability, $customerIds, $catalogTenantId) {
            $groups = FunctionGroup::query()->withoutGlobalScope('tenant')->where('tenant_id', $catalogTenantId)->get();
            foreach ($groups as $group) {
                $usedTenantIds = collect();
                foreach (['function_group_member', 'workflow_step_function_group', 'project_people', 'project_workflow_step_people', 'project_template_function_group', 'project_function_group_hours', 'tasks'] as $table) {
                    $usedTenantIds = $usedTenantIds->merge(DB::table($table)->where('function_group_id', $group->id)->pluck('tenant_id'));
                }
                $tenantIds = collect($availability[$group->id] ?? [])->map(fn ($id) => (int) $id)
                    ->merge($usedTenantIds)->intersect($customerIds)->unique();
                $group->availableTenants()->sync($tenantIds->mapWithKeys(fn ($id) => [$id => []]));
            }
        });

        return redirect()->route('admin.function-groups')->with('status', 'function-groups-updated');
    }

    private function authorizeCatalogChange(FunctionGroup $group): void
    {
        $tenantId = CurrentTenant::id();
        $catalogTenantId = FunctionGroup::catalogTenantId($tenantId);
        abort_unless($group->tenant_id === $catalogTenantId, 404);
        abort_unless(! SystemSetting::multiTenantEnabled() || $tenantId === $catalogTenantId, 403);
    }

    /**
     * @return array<string, int>
     */
    private function usageCounts(FunctionGroup $group): array
    {
        return [
            'workflow_steps' => DB::table('workflow_step_function_group')->where('function_group_id', $group->id)->count(),
            'project_people' => DB::table('project_people')->where('function_group_id', $group->id)->count(),
            'project_workflow_step_people' => DB::table('project_workflow_step_people')->where('function_group_id', $group->id)->count(),
            'project_templates' => DB::table('project_template_function_group')->where('function_group_id', $group->id)->count(),
            'project_hours' => DB::table('project_function_group_hours')->where('function_group_id', $group->id)->count(),
            'tasks' => DB::table('tasks')->where('function_group_id', $group->id)->count(),
        ];
    }
}
