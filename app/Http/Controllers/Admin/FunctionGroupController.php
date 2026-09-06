<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunctionGroup;
use App\Models\Person;
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
        $tenantId = $request->user()->tenant_id;

        $groups = FunctionGroup::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $people = Person::query()->where('tenant_id', $tenantId)
            ->with('department:id,name,short_name')
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'active', 'department_id']);

        $selectedGroup = null;
        $selectedPerson = null;
        $groupMemberIds = collect();
        $personGroupIds = collect();
        $usage = null;

        if ($request->filled('gruppe')) {
            $selectedGroup = $groups->firstWhere('id', (int) $request->query('gruppe'));
            if ($selectedGroup) {
                $groupMemberIds = $selectedGroup->members()->pluck('people.id');
                $usage = $this->usageCounts($selectedGroup);

                // Mitglieder zuerst (alphabetisch), danach der Rest
                // (ebenfalls alphabetisch) - gleiches Muster wie bei der
                // Rechte-Set-Bulk-Zuordnung, sortByDesc ist stabil.
                $people = $people->sortByDesc(fn (Person $person) => $groupMemberIds->contains($person->id))->values();
            }
        } elseif ($request->filled('person')) {
            $selectedPerson = Person::query()->where('tenant_id', $tenantId)->with('department:id,name,short_name')->find($request->query('person'));
            if ($selectedPerson) {
                $personGroupIds = $selectedPerson->functionGroups()->pluck('function_groups.id');
            }
        }

        return view('admin.function-groups.index', [
            'groups' => $groups,
            'people' => $people,
            'selectedGroup' => $selectedGroup,
            'selectedPerson' => $selectedPerson,
            'groupMemberIds' => $groupMemberIds,
            'personGroupIds' => $personGroupIds,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $name = trim((string) $request->string('name'));
        $shortName = trim((string) $request->string('short_name'));
        abort_if($name === '' || $shortName === '', 422);

        $group = FunctionGroup::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'short_name' => $shortName,
        ]);

        return redirect()->route('admin.function-groups', ['gruppe' => $group->id])->with('status', 'function-groups-updated');
    }

    public function update(Request $request, FunctionGroup $group): RedirectResponse
    {
        abort_unless($group->tenant_id === $request->user()->tenant_id, 404);

        $name = trim((string) $request->string('name'));
        $shortName = trim((string) $request->string('short_name'));
        abort_if($name === '' || $shortName === '', 422);

        $group->update([
            'name' => $name,
            'short_name' => $shortName,
            'active' => $request->boolean('active'),
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
        abort_unless($group->tenant_id === $request->user()->tenant_id, 404);

        $usage = $this->usageCounts($group);
        abort_if(array_sum($usage) > 0, 422);

        $group->delete();

        return redirect()->route('admin.function-groups');
    }

    public function updateMembers(Request $request, FunctionGroup $group): RedirectResponse
    {
        abort_unless($group->tenant_id === $request->user()->tenant_id, 404);

        $personIds = collect($request->array('person_ids'))->map(fn ($id) => (int) $id);
        $validIds = Person::query()->where('tenant_id', $group->tenant_id)->whereIn('id', $personIds)->pluck('id');

        // function_group_member.tenant_id ist NOT NULL ohne Default - sync()
        // füllt Pivot-Spalten sonst nicht automatisch, deshalb explizit je
        // Zeile mitgeben.
        $group->members()->sync($validIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $group->tenant_id]]));

        return redirect()->route('admin.function-groups', ['gruppe' => $group->id])->with('status', 'function-groups-updated');
    }

    public function updatePersonGroups(Request $request, Person $person): RedirectResponse
    {
        abort_unless($person->tenant_id === $request->user()->tenant_id, 404);

        $groupIds = collect($request->array('function_group_ids'))->map(fn ($id) => (int) $id);
        $validIds = FunctionGroup::query()->where('tenant_id', $person->tenant_id)->whereIn('id', $groupIds)->pluck('id');

        // function_group_member.tenant_id ist NOT NULL ohne Default - sync()
        // füllt Pivot-Spalten sonst nicht automatisch, deshalb explizit je
        // Zeile mitgeben.
        $person->functionGroups()->sync($validIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $person->tenant_id]]));

        return redirect()->route('admin.function-groups', ['person' => $person->id])->with('status', 'function-groups-updated');
    }

    /**
     * @return array{workflow_steps: int, project_people: int, project_workflow_step_people: int}
     */
    private function usageCounts(FunctionGroup $group): array
    {
        return [
            'workflow_steps' => $group->workflowSteps()->count(),
            'project_people' => DB::table('project_people')->where('function_group_id', $group->id)->count(),
            'project_workflow_step_people' => DB::table('project_workflow_step_people')->where('function_group_id', $group->id)->count(),
        ];
    }
}
