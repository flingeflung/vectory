<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\RecentlyViewedProject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarProjectListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_contains_favorites_and_recent_project_buttons(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee("detail: 'favorites'", false)
            ->assertSee("detail: 'recent-projects'", false)
            ->assertSee('title="Favoriten"', false)
            ->assertSee('title="Zuletzt geöffnete Projekte"', false);
    }

    public function test_recent_project_overlay_lists_current_users_projects_newest_first(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $otherUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user']);
        $older = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269921',
            'title' => 'Früher geöffnet',
            'status' => 1,
        ]);
        $newer = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269922',
            'title' => 'Zuletzt geöffnet',
            'status' => 1,
        ]);
        $foreign = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269923',
            'title' => 'Anderer Nutzer',
            'status' => 1,
        ]);
        RecentlyViewedProject::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'project_id' => $older->id,
            'viewed_at' => now()->subMinute(),
        ]);
        RecentlyViewedProject::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'project_id' => $newer->id,
            'viewed_at' => now(),
        ]);
        RecentlyViewedProject::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $otherUser->id,
            'project_id' => $foreign->id,
            'viewed_at' => now()->addMinute(),
        ]);

        $this->actingAs($user)->get(route('recent-projects.index'))
            ->assertOk()
            ->assertSeeInOrder([$newer->source_pn, $older->source_pn])
            ->assertDontSee($foreign->source_pn);
    }
}
