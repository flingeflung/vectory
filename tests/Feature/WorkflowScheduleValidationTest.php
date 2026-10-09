<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hat Termin", "Projektstart", "Projektende" und "Aktiv" sind keine Eingaben mehr (Ralf, 2026-10-09; docs/ablaufplan-konzept.md): Ein
 * benanntes Phasenende gilt als Termin, Projektstart und -ende kommen aus den Schritten "Geplant" und "Beendet", jeder Schritt ist aktiv.
 */
class WorkflowScheduleValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'super_admin']);
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'PL', 'name' => 'Ableitung', 'active' => true]);
    }

    private function step(string $title, int $sort, int $lifecycle, array $extra = []): WorkflowStep
    {
        return WorkflowStep::query()->create([...[
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $sort,
            'lifecycle_status' => $lifecycle, 'is_active' => true,
        ], ...$extra]);
    }

    private function save(WorkflowStep $step, array $fields): void
    {
        $this->actingAs($this->user)->post(route('admin.workflows.schritte.bulk-update'), [
            'workflow_id' => $this->workflow->id,
            'steps' => [$step->id => ['title' => $step->title, 'duration_days' => 1, 'lifecycle_status' => $step->lifecycle_status, ...$fields]],
        ])->assertRedirect();
    }

    public function test_a_named_phase_end_is_a_date_automatically(): void
    {
        $step = $this->step('Lektorat', 2, 2);

        $this->save($step, ['milestone_title' => 'Lektorat durchgeführt']);
        $this->assertTrue($step->fresh()->has_due_date);

        $this->save($step, ['milestone_title' => '']);
        $this->assertFalse($step->fresh()->has_due_date);
    }

    public function test_start_and_end_follow_the_status_steps(): void
    {
        $planned = $this->step('In Planung', 1, 1);
        $work = $this->step('Arbeit', 2, 2, ['is_start' => true, 'is_end' => true]);   // alte, falsche Kennzeichen
        $finished = $this->step('Projektende', 3, 3);

        $this->save($work, ['milestone_title' => '']);

        $this->assertTrue($planned->fresh()->is_start);
        $this->assertFalse($planned->fresh()->is_end);
        $this->assertFalse($work->fresh()->is_start);
        $this->assertFalse($work->fresh()->is_end);
        $this->assertTrue($finished->fresh()->is_end);
        // Status-Schritte tragen Start und Ende und gelten damit als Termin
        $this->assertTrue($planned->fresh()->has_due_date);
        $this->assertTrue($finished->fresh()->has_due_date);
    }

    public function test_saving_never_changes_the_active_switch(): void
    {
        $inactive = $this->step('Altlast', 2, 2, ['is_active' => false]);

        $this->save($inactive, ['milestone_title' => 'x']);

        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_the_step_form_no_longer_offers_the_removed_switches(): void
    {
        $this->step('In Planung', 1, 1);
        $html = $this->actingAs($this->user)->get(route('admin.workflows', ['workflow' => $this->workflow->id]))->assertOk()->getContent();

        foreach (['[is_start]', '[is_end]', '[has_due_date]', '[is_active]', '[is_market_launch]'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }
}
