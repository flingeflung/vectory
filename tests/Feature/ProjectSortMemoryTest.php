<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Die in der Projektübersicht gewählte Sortierung bleibt je Benutzer erhalten (Ralf, 2026-10-07). */
class ProjectSortMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_choice_is_remembered_for_the_bare_overview_link(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        foreach (['270001', '270002', '270003'] as $i => $pn) {
            Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => $pn, 'title' => 'P'.$pn, 'status' => 0, 'start_date' => '2027-0'.(3 - $i).'-01']);
        }
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);

        // Ohne Wahl: Standard = PN absteigend, neueste zuerst
        $this->actingAs($user)->get(route('projekte'))->assertSeeInOrder(['270003', '270002', '270001']);

        // Klick auf PN aufsteigend, danach zeigt der nackte Link (Sidebar) wieder dieselbe Reihenfolge
        $this->actingAs($user)->get(route('projekte', ['sort' => 'source_pn', 'direction' => 'asc']))->assertSeeInOrder(['270001', '270002', '270003']);
        $this->actingAs($user)->get(route('projekte'))->assertSeeInOrder(['270001', '270002', '270003']);

        // Eine andere Spalte wird ebenfalls gemerkt (Start aufsteigend: 270003 hat das früheste Datum)
        $this->actingAs($user)->get(route('projekte', ['sort' => 'start_date', 'direction' => 'asc']));
        $this->actingAs($user)->get(route('projekte'))->assertSeeInOrder(['270003', '270002', '270001']);
    }
}
