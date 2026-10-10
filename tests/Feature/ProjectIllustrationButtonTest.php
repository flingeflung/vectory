<?php

namespace Tests\Feature;

use App\Enums\GraphicOrderStatus;
use App\Models\GraphicOrder;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Der Illustrationsknopf im Projektkopf zeigt die Zahl offener Aufträge (Ralf, 2026-10-10). */
class ProjectIllustrationButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_button_shows_number_of_open_orders_only(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $project = Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '282001', 'title' => 'Illu-Knopf', 'status' => 1]);

        $this->actingAs($user)->get(route('projekte.show', $project))
            ->assertOk()
            ->assertDontSee('offen von');

        foreach ([GraphicOrderStatus::NeuerAuftrag, GraphicOrderStatus::InterneBearbeitung, GraphicOrderStatus::FertigUndAbgelegt] as $status) {
            GraphicOrder::query()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'graphic_order_status_id' => $status, 'description' => 'Bild', 'image_count' => 1]);
        }

        $this->get(route('projekte.show', $project))
            ->assertOk()
            ->assertSee('Illustrationsaufträge: 2 offen von 3');
    }
}
