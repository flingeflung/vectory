<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPreference;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zeiten › Personen & Tage: die gewählte Woche bleibt je Benutzer erhalten (Ralf, 2026-10-05).
 */
class ProjectTimesWeekMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chosen_week_is_remembered_and_the_current_week_clears_it(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $this->actingAs($user);
        $project = Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '290001', 'title' => 'P', 'status' => 0]);

        $this->get(route('projekte.zeiten.personen', ['project' => $project, 'week' => '2026-W20']))->assertOk()->assertSee('2026-W20');

        // ohne Wochenangabe (z. B. nach dem Wechsel zu einem anderen Projekt) gilt die gemerkte Woche
        $this->get(route('projekte.zeiten.personen', ['project' => $project]))->assertOk()->assertSee('2026-W20');

        $current = CarbonImmutable::today()->startOfWeek();
        $currentValue = sprintf('%04d-W%02d', $current->isoWeekYear(), $current->isoWeek());
        $this->get(route('projekte.zeiten.personen', ['project' => $project, 'week' => $currentValue]))->assertOk();
        $this->assertNull(UserPreference::configFor($user->id, UserPreference::PROJECT_TIMES)['personen_week']);

        $this->get(route('projekte.zeiten.personen', ['project' => $project]))->assertOk()->assertSee($currentValue)->assertSee('Diese Woche');
    }
}
