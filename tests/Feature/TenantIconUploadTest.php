<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TenantIconUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_upload_an_organization_logo(): void
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['notification_email' => 'organisation@example.test']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($user)->post(route('admin.kunden.update', $tenant), [
            'name' => $tenant->name,
            'short_name' => $tenant->short_name,
            'project_path' => $tenant->project_path,
            'arbeitsverzeichnis_path' => $tenant->arbeitsverzeichnis_path,
            'notification_email' => $tenant->notification_email,
            'gantt_max_projects' => 50,
            'jobload_time_grid' => 15,
            'default_weekly_hours' => 39,
            'default_vacation_days' => 30,
            'company_icon' => UploadedFile::fake()->image('organisation.png', 40, 40),
        ]);

        $response->assertRedirect();
        $filename = $tenant->fresh()->icon_filename;

        $this->assertNotNull($filename);
        $this->assertStringStartsWith('tenant-'.$tenant->id.'-', $filename);
        $this->assertFileExists(public_path('images/company-icons/'.$filename));

        $this->get(route('admin.kunden', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee(__('Organisationen'))
            ->assertSee(__('Logo auswählen'))
            ->assertSee('dataset.dirtyBaseline = window.formSnapshot($el.form)', false)
            ->assertSee('dirty = window.formIsDirty($el.form, window.__tenantsDirtyForms)', false)
            ->assertSee(rawurlencode($filename), false);

        File::delete(public_path('images/company-icons/'.$filename));
    }
}
