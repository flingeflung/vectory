<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Team;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Verwaltung der Teams (Ralf, 2026-10-06): benannte Personengruppen je Organisation, unabhängig von Funktionsgruppen.
 * Aufbau wie die anderen Verwaltungsseiten: links die Teams, rechts Stammdaten und Mitglieder des gewählten Teams.
 * Mitglieder können auch per Kundenzugriff freigegebene Personen anderer Organisationen sein (wie bei den Funktionsgruppen).
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();
        $collator = new \Collator('de_DE');
        $teams = Team::query()->withCount(['members' => fn ($query) => $query->withoutGlobalScope('tenant')])->get()
            ->sort(fn (Team $a, Team $b) => $collator->compare($a->name, $b->name))->values();

        $selected = $request->filled('team') ? $teams->firstWhere('id', (int) $request->query('team')) : null;
        $roles = $selected
            ? $selected->members()->withoutGlobalScope('tenant')->get(['people.id'])->mapWithKeys(fn (Person $person) => [$person->id => $person->pivot->role])
            : collect();

        return view('admin.teams.index', [
            'teams' => $teams,
            'selected' => $selected,
            'roles' => $roles,
            'people' => $this->eligiblePeople($request, $tenantId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $team = Team::query()->create([...$data, 'active' => true]);

        return redirect()->route('admin.teams', ['team' => $team->id])->with('status', 'teams-updated');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $data = $this->validated($request, $team);
        $team->update([...$data, 'active' => $request->boolean('active')]);

        // Nur Personen übernehmen, die in dieser Organisation sichtbar sind; Tenant der Zeile = Tenant des Teams.
        // members[<Personen-ID>] = member|lead|deputy
        $submitted = collect($request->array('members'));
        $sync = $this->eligiblePeople($request, $team->tenant_id)
            ->filter(fn (Person $person) => $submitted->has($person->id))
            ->mapWithKeys(fn (Person $person) => [$person->id => [
                'tenant_id' => $team->tenant_id,
                'role' => in_array($submitted->get($person->id), Team::ROLES, true) ? $submitted->get($person->id) : Team::ROLE_MEMBER,
            ]]);
        $team->members()->sync($sync);

        return redirect()->route('admin.teams', ['team' => $team->id])->with('status', 'teams-updated');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return redirect()->route('admin.teams')->with('status', 'teams-updated');
    }

    /** @return array{name: string, short_name: ?string, description: ?string} */
    private function validated(Request $request, ?Team $team = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->where('tenant_id', $team?->tenant_id ?? CurrentTenant::id())->ignore($team?->id)],
            'short_name' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.unique' => __('Ein Team mit diesem Namen gibt es in dieser Organisation schon.'),
        ]);

        return [
            'name' => trim($data['name']),
            'short_name' => ($data['short_name'] ?? null) !== null ? trim($data['short_name']) ?: null : null,
            'description' => ($data['description'] ?? null) !== null ? trim($data['description']) ?: null : null,
        ];
    }

    /** Personen, die in der Organisation sichtbar sind (auch ausgeliehene), alphabetisch. */
    private function eligiblePeople(Request $request, int $tenantId): \Illuminate\Support\Collection
    {
        return Person::query()->withoutGlobalScope('tenant')->visibleInTenant($tenantId)
            ->visibleToRole($request->user()->role)
            ->orderBy('last_name')->orderBy('first_name')
            ->with(['tenant' => fn ($query) => $query->withoutGlobalScopes()->select('id', 'name', 'short_name')])
            ->get(['id', 'tenant_id', 'first_name', 'last_name', 'active']);
    }
}
