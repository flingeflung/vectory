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
        // Start-Datum in umgekehrter PN-Reihenfolge, damit Standard (Start absteigend) und PN-Sortierung unterscheidbar sind
        foreach (['270001' => '2027-03-01', '270002' => '2027-02-01', '270003' => '2027-01-01'] as $pn => $start) {
            Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => $pn, 'title' => 'P'.$pn, 'status' => 0, 'start_date' => $start]);
        }
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);

        // Ohne Wahl: Standard (Start absteigend)
        $this->actingAs($user)->get(route('projekte'))->assertSeeInOrder(['270001', '270002', '270003']);

        // Klick auf PN absteigend, danach zeigt der nackte Link (Sidebar) wieder dieselbe Reihenfolge
        $this->actingAs($user)->get(route('projekte', ['sort' => 'source_pn', 'direction' => 'desc']))->assertSeeInOrder(['270003', '270002', '270001']);
        $this->actingAs($user)->get(route('projekte'))->assertSeeInOrder(['270003', '270002', '270001']);

        // Aufsteigend wird ebenfalls gemerkt
        $this->actingAs($user)->get(route('projekte', ['sort' => 'source_pn', 'direction' => 'asc']));
        $this->actingAs($user)->get(route('projekte'))->assertSeeInOrder(['270001', '270002', '270003']);
    }
}
