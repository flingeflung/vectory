<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_acknowledgement_feature_can_be_enabled_on_separate_superadmin_page(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);

        $this->assertFalse(SystemSetting::criticalProjectAcknowledgementEnabled());
        $this->assertNotContains(
            'admin.superadmin',
            collect(AdminNav::groups())->flatten(1)->pluck('route')->all(),
        );

        $this->actingAs($user)->get(route('admin.superadmin'))
            ->assertOk()
            ->assertSee('Kenntnisnahme bei kritischen Projekten verwenden');
        $this->assertFalse(session()->has('admin.last_tab_url'));

        $this->post(route('admin.superadmin.update'), [
            'critical_project_acknowledgement_enabled' => '1',
        ])->assertRedirect(route('admin.superadmin'));

        $this->assertTrue(SystemSetting::criticalProjectAcknowledgementEnabled());
    }
}
