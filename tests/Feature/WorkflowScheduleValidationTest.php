<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowScheduleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_or_end_step_requires_a_due_date(): void
    {
        [$user, $workflow, $step] = $this->workflowStep();

        $this->actingAs($user)->post(route('admin.workflows.schritte.bulk-update'), [
            'workflow_id' => $workflow->id,
            'steps' => [
                $step->id => [
                    'title' => $step->title,
                    'duration_days' => 0,
                    'is_end' => 1,
                ],
            ],
        ])->assertStatus(422)
            ->assertSee('Ein Schritt für Projektstart oder Projektende muss einen Termin haben.');

        $this->assertFalse($step->fresh()->is_end);
        $this->assertFalse($step->fresh()->has_due_date);
    }

    public function test_end_step_with_due_date_is_valid(): void
    {
        [$user, $workflow, $step] = $this->workflowStep();

        $this->actingAs($user)->post(route('admin.workflows.schritte.bulk-update'), [
            'workflow_id' => $workflow->id,
            'steps' => [
                $step->id => [
                    'title' => $step->title,
                    'duration_days' => 0,
                    'is_end' => 1,
                    'has_due_date' => 1,
                ],
            ],
        ])->assertRedirect();

        $this->assertTrue($step->fresh()->is_end);
        $this->assertTrue($step->fresh()->has_due_date);
    }

    private function workflowStep(): array
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'short_name' => 'PL',
            'name' => 'Plausibilitätsprüfung',
            'active' => true,
        ]);
        $step = WorkflowStep::query()->create([
            'tenant_id' => $tenant->id,
            'workflow_id' => $workflow->id,
            'title' => 'Projektende',
            'sort' => 1,
            'is_active' => true,
            'is_end' => false,
            'has_due_date' => false,
        ]);

        return [$user, $workflow, $step];
    }
}
