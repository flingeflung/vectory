<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ProjectColumnCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spalte "Aufwandsprofil" in der Projektübersicht (Ralf, 2026-10-05), einblendbar über den Anzeigefilter.
 */
class ProjectTemplateColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_column_is_offered_and_shows_the_profile_name_when_enabled(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $this->actingAs($user);

        $this->assertContains('project_template', array_column(ProjectColumnCatalog::effectiveFor($user), 'key'));

        $template = ProjectTemplate::query()->create([
            'tenant_id' => $tenant->id, 'name' => 'Kurzes Print-Profil', 'format' => 1, 'reusable_content_share' => 1, 'languages_count' => 1,
            'product_maturity' => 1, 'product_change_delays' => 1, 'contact_availability' => 1, 'localizer_availability' => 1,
            'software_share' => 1, 'product_complexity' => 1, 'print_variants_count' => 1, 'images_count' => 1,
            'duration_value' => 1, 'duration_unit' => 'weeks',
        ]);
        Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '280001', 'title' => 'Mit Profil', 'status' => 0, 'project_template_id' => $template->id]);

        $set = \App\Models\DisplayFilterSet::query()->where('user_id', $user->id)->where('is_active', true)->firstOrFail();
        $this->post(route('projekte.anzeigefilter.update'), [
            'set_id' => $set->id,
            'order' => ['title', 'project_template'],
            'visible' => ['title', 'project_template'],
        ])->assertRedirect();

        $this->get(route('projekte'))->assertOk()->assertSee('Aufwandsprofil')->assertSee('Kurzes Print-Profil');
    }
}
