<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use App\Support\Morph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MorphTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
    }

    public function test_super_admin_morphs_into_a_user_with_a_permission_set_and_back(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $super = $this->superAdmin($tenant);
        $template = PermissionTemplate::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'name' => 'Nur Ansehen']);
        $template->permissions()->attach(Permission::query()->where('key', 'project.view')->firstOrFail()->id);

        $this->actingAs($super);
        $this->assertTrue(Gate::allows('access-superadmin'));

        $this->postJson(route('morphen.start'), ['role' => 'user', 'template_id' => $template->id])->assertOk();

        $user = auth()->user();
        $this->assertSame('user', $user->role);
        $this->assertTrue(Gate::allows('project.view'));
        $this->assertFalse(Gate::allows('project.edit'));
        $this->assertFalse(Gate::allows('access-admin'));
        $this->assertFalse(Gate::allows('access-superadmin'));
        $this->get(route('admin.personen'))->assertRedirect();   // Admin-Seiten sind für die Rolle User gesperrt

        // Beenden geht auch gemorpht
        $this->deleteJson(route('morphen.ende'))->assertOk();
        $this->assertSame('super_admin', auth()->user()->role);
        $this->assertTrue(Gate::allows('access-superadmin'));
        $this->get(route('admin.personen'))->assertOk();
    }

    public function test_only_a_real_super_admin_may_morph(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $central = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'central_admin']);
        $this->actingAs($central)->postJson(route('morphen.start'), ['role' => 'user'])->assertForbidden();
        $this->deleteJson(route('morphen.ende'))->assertForbidden();
        $this->assertSame('central_admin', auth()->user()->role);
    }

    public function test_morphed_organization_admin_works_in_the_selected_organization_only(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Maschinen', 'short_name' => 'MA']);
        $other = Tenant::query()->create(['name' => 'Elektro', 'short_name' => 'EL']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $super = $this->superAdmin($home);

        $this->actingAs($super)->withSession(['active_tenant_id' => $customer->id]);
        $this->postJson(route('morphen.start'), ['role' => 'organization_admin'])->assertOk();

        $this->assertSame($customer->id, CurrentTenant::id());
        $this->assertTrue(Gate::allows('access-admin'));
        $this->assertFalse(Gate::allows('access-central-admin'));
        $this->assertFalse(CurrentTenant::userCanAccess(auth()->user(), $other->id));
        $this->assertSame('Organisations-Admin', Morph::label());

        // Der gespeicherte Benutzer ist unverändert
        $this->assertSame('super_admin', $super->fresh()->getRawOriginal('role'));
        $this->assertSame($home->id, (int) $super->fresh()->getRawOriginal('tenant_id'));
    }

    public function test_morphing_does_not_touch_other_users(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $super = $this->superAdmin($tenant);
        $otherSuper = $this->superAdmin($tenant);
        $this->actingAs($super)->postJson(route('morphen.start'), ['role' => 'user'])->assertOk();

        $this->assertSame('user', auth()->user()->role);
        $this->assertSame('super_admin', $otherSuper->role);
    }

    public function test_morph_dialog_offers_the_permission_sets_of_all_organizations(): void
    {
        $home = Tenant::query()->firstOrFail();
        $customer = Tenant::query()->create(['name' => 'Maschinen', 'short_name' => 'MA']);
        PermissionTemplate::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $home->id, 'name' => 'Heimat-Set']);
        PermissionTemplate::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $customer->id, 'name' => 'Kunden-Set']);
        $super = $this->superAdmin($home);

        $this->actingAs($super)->get(route('dashboard'))->assertOk()
            ->assertSee('Heimat-Set')
            ->assertSee('Kunden-Set');
    }

    public function test_banner_and_button_appear_for_the_super_admin_only(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $super = $this->superAdmin($tenant);
        $this->actingAs($super)->get(route('dashboard'))->assertOk()
            ->assertSee(__('Morphen: Sicht einer anderen Rolle prüfen'))
            ->assertDontSee(__('Morphen beenden'));

        $this->postJson(route('morphen.start'), ['role' => 'organization_admin'])->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertSee(__('Morphen beenden'))->assertSee(__('Gemorpht als'));

        $plain = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $this->actingAs($plain)->get(route('dashboard'))->assertOk()
            ->assertDontSee(__('Morphen: Sicht einer anderen Rolle prüfen'));
    }

    public function test_morphed_user_keeps_the_home_organization_but_can_stay_in_the_selected_one(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Maschinen', 'short_name' => 'MA']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $super = $this->superAdmin($home);

        $this->actingAs($super)->withSession(['active_tenant_id' => $customer->id]);
        $this->postJson(route('morphen.start'), ['role' => 'user'])->assertOk();

        // Die Person (und damit z. B. die Zeiterfassung) gehört weiter zur Heimat-Organisation ...
        $this->assertSame($home->id, auth()->user()->tenant_id);
        // ... gearbeitet wird aber weiter in der beim Morphen gewählten Organisation.
        $this->assertSame($customer->id, CurrentTenant::id());
        // ... und sie steht im Organisations-Umschalter.
        $this->assertContains($customer->id, CurrentTenant::availableTenants()->pluck('id')->all());
    }
}
