<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_started_planned_project_and_explains_rule(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);
        $critical = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269901',
            'title' => 'Verspäteter Projektstart',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);
        $uncritical = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269902',
            'title' => 'Künftiges Projekt',
            'status' => 0,
            'start_date' => today()->addDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee($critical->source_pn)
            ->assertSee('Projektstart erreicht, Status noch geplant')
            ->assertSee('Mögliche Lösung')
            ->assertDontSee($uncritical->source_pn);

        $this->get(route('projekte.show', $critical))
            ->assertOk()
            ->assertSee('Fehlercheck: 1 Befund');
    }

    public function test_reason_filter_keeps_only_matching_findings(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269903',
            'title' => 'Filterprojekt',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index', ['reason' => 'staffing.missing']))
            ->assertOk()
            ->assertSee('keine kritischen Projekte gefunden')
            ->assertDontSee('269903');
    }

    public function test_planned_hours_resolve_template_from_another_organization(): void
    {
        $activeTenant = Tenant::query()->firstOrFail();
        $otherTenant = Tenant::query()->create(['name' => 'Andere Organisation', 'short_name' => 'AND']);
        $user = User::factory()->create(['tenant_id' => $activeTenant->id, 'role' => 'super_admin']);
        $this->actingAs($user);

        $template = ProjectTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Fremde Aufwandsschablone',
            'format' => 1,
            'reusable_content_share' => 1,
            'languages_count' => 1,
            'product_maturity' => 1,
            'product_change_delays' => 1,
            'contact_availability' => 1,
            'localizer_availability' => 1,
            'software_share' => 1,
            'product_complexity' => 1,
            'print_variants_count' => 1,
            'images_count' => 1,
            'duration_value' => 1,
            'duration_unit' => 'Tage',
            'active' => true,
        ]);
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'source_pn' => '269904',
            'title' => 'Projekt einer anderen Organisation',
            'status' => 1,
            'project_template_id' => $template->id,
        ]);

        $this->assertTrue($project->projectTemplate()->exists());
        $this->assertSame(0.0, $project->effectivePlannedHours());
    }
}
