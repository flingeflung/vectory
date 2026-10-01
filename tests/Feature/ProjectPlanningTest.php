<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_and_utilization_views_use_person_planned_hours(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['is_home_tenant' => true]);
        $person = $this->person($tenant, 'Planung');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'person_id' => $person->id,
            'role' => 'super_admin',
        ]);
        DB::table('person_weekly_hours')->insert([
            'tenant_id' => $tenant->id,
            'person_id' => $person->id,
            'hours' => 40,
            'valid_from' => null,
            'valid_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('calendar_entries')->insert([
            'person_id' => $person->id,
            'created_by_user_id' => $user->id,
            'type' => 'absence',
            'starts_on' => '2026-10-02',
            'ends_on' => '2026-10-02',
            'note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $groupId = DB::table('function_groups')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Redaktion',
            'short_name' => 'Red',
            'sort' => 1,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('function_group_member')->insert([
            'tenant_id' => $tenant->id,
            'function_group_id' => $groupId,
            'person_id' => $person->id,
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'tenant_id' => $tenant->id,
            'source_pn' => 'P-PLAN',
            'title' => 'Planungsprojekt',
            'status' => 1,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-05',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_people')->insert([
            'tenant_id' => $tenant->id,
            'project_id' => $projectId,
            'function_group_id' => $groupId,
            'person_id' => $person->id,
            'is_primary' => false,
            'planned_hours' => 9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('planung.projektplanung', [
            'year' => 2026,
            'month' => 10,
            'person_filter' => 1,
            'people' => [$person->id],
        ]))->assertOk()->assertSee('Planungsprojekt');

        $this->assertSame(9.0, $response->viewData('projectRowsByPerson')->get($person->id)->first()['plannedHours']);
        $octoberFirst = $response->viewData('utilizationByPerson')->get($person->id)->get('2026-10-01');
        $this->assertEqualsWithDelta(8.0, $octoberFirst['work'], 0.001);
        $this->assertEqualsWithDelta(3.0, $octoberFirst['project'], 0.001);
        $this->assertEqualsWithDelta(5.0, $octoberFirst['remaining'], 0.001);
        $octoberSecond = $response->viewData('utilizationByPerson')->get($person->id)->get('2026-10-02');
        $this->assertEqualsWithDelta(8.0, $octoberSecond['absence'], 0.001);
        $this->assertEqualsWithDelta(-3.0, $octoberSecond['remaining'], 0.001);

        $this->get(route('planung.projektplanung', [
            'content' => 'utilization',
            'year' => 2026,
            'month' => 10,
            'person_filter' => 1,
            'people' => [$person->id],
        ]))->assertOk()->assertSee(__('Geplante Projektstunden'));

        $this->get(route('projekte.show', $projectId))
            ->assertOk()
            ->assertSee('project_people_hours['.$groupId.']['.$person->id.']', false)
            ->assertSee('value="9"', false);
    }

    public function test_person_selection_is_remembered_and_limited_to_eligible_people(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);
        $selectedPerson = $this->person($tenant, 'Auswahl');
        $otherPerson = $this->person($tenant, 'Andere');

        $this->actingAs($user)
            ->get(route('planung.projektplanung', [
                'person_filter' => 1,
                'people' => [$selectedPerson->id, 999999],
            ]))
            ->assertOk()
            ->assertViewHas('selectedPersonIds', fn ($ids) => $ids->all() === [$selectedPerson->id]);

        $this->assertSame(
            ['person_ids' => [$selectedPerson->id]],
            UserPreference::configFor($user->id, UserPreference::PROJECT_PLANNING),
        );

        $this->get(route('planung.projektplanung'))
            ->assertOk()
            ->assertViewHas('selectedPersonIds', fn ($ids) => $ids->all() === [$selectedPerson->id])
            ->assertViewHas('selectedPersonIds', fn ($ids) => ! $ids->contains($otherPerson->id));

        $selectedPerson->update(['resource_planning' => false]);

        $this->get(route('planung.projektplanung'))
            ->assertOk()
            ->assertViewHas('selectedPersonIds', fn ($ids) => $ids->isEmpty());

        $this->assertSame(
            ['person_ids' => []],
            UserPreference::configFor($user->id, UserPreference::PROJECT_PLANNING),
        );
    }

    public function test_home_member_sees_qualified_home_and_selected_customer_people(): void
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true, 'name' => 'Heimat']);
        $customer = Tenant::query()->create(['name' => 'Kunde']);
        $otherCustomer = Tenant::query()->create(['name' => 'Anderer Kunde']);
        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => 'super_admin']);
        $homePerson = $this->person($home, 'Heimat');
        $customerPerson = $this->person($customer, 'Kunde');
        $this->person($otherCustomer, 'Fremd');
        $this->person($home, 'Inaktiv', active: false);
        $this->person($home, 'Ohne Planung', resourcePlanning: false);

        $response = $this->actingAs($user)
            ->withSession(['active_tenant_id' => $customer->id])
            ->get(route('planung.projektplanung'))
            ->assertOk();

        $ids = $response->viewData('personGroups')->flatten(1)->pluck('id');
        $this->assertEqualsCanonicalizing([$homePerson->id, $customerPerson->id], $ids->all());
    }

    public function test_customer_member_sees_customer_people_and_only_assigned_home_people(): void
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true, 'name' => 'Heimat']);
        $customer = Tenant::query()->create(['name' => 'Kunde']);
        $ownPerson = $this->person($customer, 'Eigen');
        $customerColleague = $this->person($customer, 'Kollege');
        $assignedHomePerson = $this->person($home, 'Zugewiesen');
        $this->person($home, 'Nicht zugewiesen');
        DB::table('person_tenant')->insert([
            'person_id' => $assignedHomePerson->id,
            'tenant_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'tenant_id' => $customer->id,
            'person_id' => $ownPerson->id,
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($user)
            ->get(route('planung.projektplanung', ['view' => 'year', 'year' => 2026]))
            ->assertOk()
            ->assertViewHas('displayMode', 'year');

        $ids = $response->viewData('personGroups')->flatten(1)->pluck('id');
        $this->assertEqualsCanonicalizing([$ownPerson->id, $customerColleague->id, $assignedHomePerson->id], $ids->all());
    }

    private function person(Tenant $tenant, string $lastName, bool $active = true, bool $resourcePlanning = true): Person
    {
        return Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Test',
            'last_name' => $lastName,
            'active' => $active,
            'resource_planning' => $resourcePlanning,
        ]);
    }
}
