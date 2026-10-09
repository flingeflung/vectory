<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\PlanningBaseLoad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Grundlast: Ansehen am Planungsrecht, Ändern nur mit dem eigenen Recht planning.base_load.edit (Ralf, 2026-10-09). */
class PlanningBaseLoadEditRightTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
    }

    private function userWith(array $keys): User
    {
        $set = PermissionTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Set '.implode('+', $keys)]);
        $set->permissions()->attach(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $person = Person::query()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'P'.random_int(1000, 9999), 'active' => true, 'permission_template_id' => $set->id]);

        return User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'role' => 'user']);
    }

    private function baseLoad(): PlanningBaseLoad
    {
        return PlanningBaseLoad::query()->create(['tenant_id' => $this->tenant->id, 'year' => 2027, 'name' => 'Meetings', 'calculation_type' => 'weekly', 'value' => 2, 'valid_from' => '2027-01-01', 'valid_to' => '2027-12-31']);
    }

    public function test_the_right_exists_and_is_part_of_templates_that_had_the_planning_right(): void
    {
        $this->assertDatabaseHas('permissions', ['key' => 'planning.base_load.edit']);
    }

    public function test_viewing_works_without_the_edit_right_but_changing_does_not(): void
    {
        $viewer = $this->userWith(['planning.view']);
        $load = $this->baseLoad();

        $this->actingAs($viewer)->get(route('planung.grundlast', ['year' => 2027]))->assertOk()
            ->assertSee('Meetings')->assertSee('Sie dürfen die Grundlast ansehen, aber nicht ändern.')->assertDontSee('Grundlast anlegen');
        $this->actingAs($viewer)->get(route('planung.grundlast-person', ['year' => 2027]))->assertOk();

        $this->actingAs($viewer)->call('DELETE', route('planung.grundlast.destroy', $load), ['year' => 2027])->assertForbidden();
        $this->actingAs($viewer)->call('PUT', route('planung.grundlast.update', $load), ['year' => 2027, 'name' => 'X', 'calculation_type' => 'weekly', 'value' => 9, 'valid_from' => '2027-01-01', 'valid_to' => '2027-12-31'])->assertForbidden();
        $this->actingAs($viewer)->call('POST', route('planung.grundlast.copy-previous'), ['year' => 2027])->assertForbidden();
        $this->assertModelExists($load);
        $this->assertSame('Meetings', $load->fresh()->name);
    }

    public function test_with_both_rights_the_base_load_can_be_changed_and_admins_always_may(): void
    {
        $editor = $this->userWith(['planning.view', 'planning.base_load.edit']);
        $load = $this->baseLoad();

        $this->actingAs($editor)->get(route('planung.grundlast', ['year' => 2027]))->assertOk()->assertSee('Grundlast anlegen')->assertDontSee('Sie dürfen die Grundlast ansehen');
        $this->actingAs($editor)->call('DELETE', route('planung.grundlast.destroy', $load), ['year' => 2027])->assertRedirect();
        $this->assertModelMissing($load);

        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->assertTrue($admin->can('planning.base_load.edit'));
    }

    public function test_the_edit_right_alone_does_not_open_the_pages(): void
    {
        $this->actingAs($this->userWith(['planning.base_load.edit']))->get(route('planung.grundlast', ['year' => 2027]))->assertForbidden();
    }
}
