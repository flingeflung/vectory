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

    public function test_the_project_filter_can_select_a_profile(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $this->actingAs(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']));
        $make = fn (string $name) => ProjectTemplate::query()->create([
            'tenant_id' => $tenant->id, 'name' => $name, 'format' => 1, 'reusable_content_share' => 1, 'languages_count' => 1,
            'product_maturity' => 1, 'product_change_delays' => 1, 'contact_availability' => 1, 'localizer_availability' => 1,
            'software_share' => 1, 'product_complexity' => 1, 'print_variants_count' => 1, 'images_count' => 1,
            'duration_value' => 1, 'duration_unit' => 'weeks',
        ]);
        $short = $make('Kurzprofil');
        $long = $make('Langprofil');
        Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '280010', 'title' => 'Projekt Kurz', 'status' => 0, 'project_template_id' => $short->id]);
        Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '280011', 'title' => 'Projekt Lang', 'status' => 0, 'project_template_id' => $long->id]);

        $this->assertContains('project_template_id', array_column(\App\Support\ProjectFilterCatalog::available($tenant->id), 'key'));
        $this->get(route('projekte', ['filter' => ['project_template_id' => $short->id]]))
            ->assertOk()->assertSee('Projekt Kurz')->assertDontSee('Projekt Lang');
    }
}
