<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AccessLevel;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_access_levels_have_fixed_scope_and_do_not_need_a_rights_set(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Kunde', 'short_name' => 'K', 'active' => true]);

        $centralAdmin = User::factory()->create(['tenant_id' => $home->id, 'role' => AccessLevel::CENTRAL_ADMIN]);
        $organizationAdmin = User::factory()->create(['tenant_id' => $customer->id, 'role' => AccessLevel::ORGANIZATION_ADMIN]);

        $this->assertTrue(Gate::forUser($centralAdmin)->allows('access-admin'));
        $this->assertTrue(Gate::forUser($centralAdmin)->allows('planning.view'));
        $this->assertFalse(Gate::forUser($centralAdmin)->allows('access-superadmin'));
        $this->assertTrue(CurrentTenant::userCanAccess($centralAdmin, $customer->id));
        $this->assertTrue(Gate::forUser($organizationAdmin)->allows('access-admin'));
        $this->assertTrue(Gate::forUser($organizationAdmin)->allows('planning.view'));
        $this->assertFalse(CurrentTenant::userCanAccess($organizationAdmin, $home->id));
    }

    public function test_standard_user_gets_features_from_rights_set_but_never_admin_access(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $permission = Permission::query()->where('key', 'planning.view')->firstOrFail();
        $set = PermissionTemplate::query()->create(['tenant_id' => $tenant->id, 'name' => 'Planung']);
        $set->permissions()->attach($permission);
        $person = Person::query()->create([
            'tenant_id' => $tenant->id,
            'last_name' => 'Planer',
            'short_name' => 'PL',
            'active' => true,
            'permission_template_id' => $set->id,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'person_id' => $person->id,
            'role' => AccessLevel::USER,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('planning.view'));
        $this->assertFalse(Gate::forUser($user)->allows('access-admin'));

        $centralAdmin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => AccessLevel::CENTRAL_ADMIN]);
        $this->actingAs($centralAdmin)->post(route('admin.rechte.personen.update', $person), [
            'permission_template_id' => $set->id,
        ])->assertRedirect();

        $this->assertSame(AccessLevel::USER, $user->fresh()->role);
    }
}
