<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAdminTest extends TestCase
{
    use RefreshDatabase;

    private function person(Tenant $tenant, string $lastName, bool $active = true): Person
    {
        return Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'last_name' => $lastName, 'first_name' => 'Test', 'active' => $active,
        ]);
    }

    public function test_admin_creates_updates_and_deletes_a_team_with_roles(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        $anna = $this->person($tenant, 'Anna');
        $bert = $this->person($tenant, 'Bert');
        $cora = $this->person($tenant, 'Cora', false);
        $this->actingAs($admin);

        $this->post(route('admin.teams.store'), ['name' => 'Team 1', 'short_name' => 'T1'])->assertRedirect();
        $team = Team::query()->where('name', 'Team 1')->firstOrFail();
        $this->assertSame($tenant->id, $team->tenant_id);

        // Doppelter Name in derselben Organisation wird abgelehnt.
        $this->post(route('admin.teams.store'), ['name' => 'Team 1'])->assertSessionHasErrors('name');

        $this->post(route('admin.teams.update', $team), [
            'name' => 'Team Eins', 'short_name' => 'T1', 'description' => 'Test', 'active' => 1,
            'members' => [$anna->id => 'lead', $bert->id => 'deputy', $cora->id => 'member', 999999 => 'member'],
        ])->assertRedirect();

        $team->refresh();
        $this->assertSame('Team Eins', $team->name);
        $roles = $team->members()->withoutGlobalScope('tenant')->get()->mapWithKeys(fn ($person) => [$person->id => $person->pivot->role]);
        $this->assertSame([$anna->id => 'lead', $bert->id => 'deputy', $cora->id => 'member'], $roles->all());

        $this->get(route('admin.teams', ['team' => $team->id]))->assertOk()->assertSee('Team Eins')->assertSee('Anna');

        // Mitglied entfernen und ungültige Rolle wird zu "member".
        $this->post(route('admin.teams.update', $team), ['name' => 'Team Eins', 'active' => 1, 'members' => [$bert->id => 'boss']])->assertRedirect();
        $this->assertSame(['member'], $team->members()->withoutGlobalScope('tenant')->get()->map(fn ($person) => $person->pivot->role)->all());

        $this->delete(route('admin.teams.destroy', $team))->assertRedirect(route('admin.teams'));
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('people', ['id' => $bert->id]);
    }

    public function test_plain_user_has_no_access_and_teams_of_other_organizations_stay_hidden(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $other = Tenant::query()->create(['name' => 'Andere Orga', 'short_name' => 'AO']);
        $foreign = Team::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $other->id, 'name' => 'Fremdes Team']);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $this->actingAs($user)->get(route('admin.teams'))->assertRedirect();

        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($admin)->get(route('admin.teams'))->assertOk()->assertDontSee('Fremdes Team');
        // Fremde Teams sind nicht erreichbar: die Anfrage wird abgewiesen, der Name bleibt unverändert.
        $this->post(route('admin.teams.update', $foreign), ['name' => 'Gekapert']);
        $this->assertDatabaseHas('teams', ['id' => $foreign->id, 'name' => 'Fremdes Team']);
    }

    public function test_project_people_field_offers_active_teams_for_assignment(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        $anna = $this->person($tenant, 'Anna');
        $group = \App\Models\FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'name' => 'Technische Redaktion', 'short_name' => 'TR']);
        $group->members()->attach($anna->id, ['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '279992', 'title' => 'Teamprojekt', 'status' => 1]);

        $active = Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team Aktiv']);
        $active->members()->attach($anna->id, ['tenant_id' => $tenant->id, 'role' => 'lead']);
        Team::query()->create(['tenant_id' => $tenant->id, 'name' => 'Team Inaktiv', 'active' => false]);

        $this->actingAs($admin)->get(route('projekte.projektbeteiligte.show', $project))
            ->assertOk()
            ->assertSee(__('Team zuweisen'))
            ->assertSee('Team Aktiv')
            ->assertDontSee('Team Inaktiv')
            ->assertSee('data-person="'.$anna->id.'"', false)
            ->assertSee('data-group="'.$group->id.'"', false);
    }
}
