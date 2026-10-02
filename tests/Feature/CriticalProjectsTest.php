<?php

namespace Tests\Feature;

use App\Models\Project;
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
            ->assertSee('Lösungshinweis')
            ->assertDontSee($uncritical->source_pn);
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
}
