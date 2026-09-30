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
