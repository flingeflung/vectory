<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDeactivationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $home;

    private Tenant $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::forgetInactiveCache();

        $this->home = Tenant::query()->firstOrFail();
        $this->home->update(['is_home_tenant' => true]);
        $this->customer = Tenant::query()->create(['name' => 'Kunde A', 'short_name' => 'KA']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
    }

    protected function tearDown(): void
    {
        Tenant::forgetInactiveCache();
        parent::tearDown();
    }

    private function deactivate(): void
    {
        $this->customer->update(['is_active' => false]);
    }

    public function test_data_of_a_deactivated_organization_is_hidden_even_where_the_tenant_scope_is_bypassed(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'central_admin']));
        Project::query()->create(['tenant_id' => $this->customer->id, 'source_pn' => '270001', 'title' => 'Kundenprojekt', 'status' => 0]);
        Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->customer->id, 'last_name' => 'Kundenperson', 'active' => true]);

        $this->assertSame(1, Project::query()->withoutGlobalScope('tenant')->count());
        $this->assertSame(1, Person::query()->withoutGlobalScope('tenant')->count());

        $this->deactivate();

        $this->assertSame(0, Project::query()->withoutGlobalScope('tenant')->count());
        $this->assertSame(0, Person::query()->withoutGlobalScope('tenant')->count());
        // Nur wer die zweite Schutzregel bewusst abschaltet (Organisationsverwaltung), sieht die Daten noch.
        $this->assertSame(1, Project::query()->withoutGlobalScopes()->count());

        $this->customer->update(['is_active' => true]);
        $this->assertSame(1, Project::query()->withoutGlobalScope('tenant')->count());
    }

    public function test_users_of_a_deactivated_organization_cannot_log_in(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->customer->id]);
        $this->deactivate();

        $this->post(route('login'), ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Ihre Organisation ist in dieser Vectory-Installation nicht mehr aktiv. Eine Anmeldung ist daher nicht möglich. Bitte wenden Sie sich bei Fragen an Ihre zuständige Administration.']);
        $this->assertGuest();
    }

    public function test_a_logged_in_user_is_logged_out_on_the_next_request_when_the_organization_is_deactivated(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->customer->id]);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->deactivate();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => 'Ihre Organisation ist in dieser Vectory-Installation nicht mehr aktiv. Sie wurden daher abgemeldet. Bitte wenden Sie sich bei Fragen an Ihre zuständige Administration.']);
        $this->assertGuest();
    }

    public function test_deactivated_organization_is_not_selectable(): void
    {
        $central = User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'central_admin']);
        $this->actingAs($central);

        $this->assertTrue(CurrentTenant::availableTenants()->contains('id', $this->customer->id));
        $this->assertTrue(CurrentTenant::userCanAccess($central, $this->customer->id));

        $this->deactivate();

        $this->assertFalse(CurrentTenant::availableTenants()->contains('id', $this->customer->id));
        $this->assertFalse(CurrentTenant::userCanAccess($central, $this->customer->id));
    }

    public function test_only_super_admin_can_switch_and_the_home_organization_stays_active(): void
    {
        $organizationAdmin = User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'central_admin']);
        $this->actingAs($organizationAdmin)
            ->post(route('admin.kunden.active', $this->customer), ['active' => 0])
            ->assertRedirect();
        $this->assertTrue($this->customer->fresh()->is_active);

        $super = User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'super_admin']);
        $this->actingAs($super)
            ->post(route('admin.kunden.active', $this->home), ['active' => 0])
            ->assertRedirect()
            ->assertSessionHas('error', 'Die Heimat-Organisation kann nicht deaktiviert werden.');
        $this->assertTrue($this->home->fresh()->is_active);

        $this->post(route('admin.kunden.active', $this->customer), ['active' => 0])->assertRedirect();
        $this->assertFalse($this->customer->fresh()->is_active);

        $this->get(route('admin.kunden', ['tenant' => $this->customer->id]))
            ->assertOk()
            ->assertSee('Kunde A [i]')
            ->assertSee('Reaktivieren');

        $this->post(route('admin.kunden.active', $this->customer), ['active' => 1])->assertRedirect();
        $this->assertTrue($this->customer->fresh()->is_active);
    }

    public function test_a_link_to_an_unreachable_project_shows_a_friendly_notice_instead_of_technical_text(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'central_admin']));

        $this->get(route('projekte.show', 999999))
            ->assertRedirect()
            ->assertSessionHas('notice', 'Das Projekt wurde nicht gefunden oder steht nicht zur Verfügung.')
            ->assertSessionMissing('error');
    }
}
