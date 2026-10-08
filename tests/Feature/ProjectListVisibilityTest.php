<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Projekte ohne Berechtigung erscheinen nicht in der Liste, außer die Organisation erlaubt es (Ralf, 2026-10-08). */
class ProjectListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_are_hidden_without_right_unless_organization_allows_it(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '280001', 'title' => 'Sichtbarkeitstest', 'status' => 0, 'start_date' => '2027-03-01']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $this->assertFalse($user->can('project.view'));

        $this->assertFalse((bool) $tenant->show_unopenable_projects);
        $this->actingAs($user)->get(route('projekte'))->assertOk()->assertDontSee('280001');

        $tenant->update(['show_unopenable_projects' => true]);
        $this->actingAs($user)->get(route('projekte'))->assertOk()->assertSee('280001');

        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        $tenant->update(['show_unopenable_projects' => false]);
        $this->actingAs($admin)->get(route('projekte'))->assertOk()->assertSee('280001');
    }
}
