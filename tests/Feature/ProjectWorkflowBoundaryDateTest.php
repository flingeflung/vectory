<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectWorkflowBoundaryDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_scheduled_start_step_clears_stale_project_start_when_its_date_is_empty(): void
    {
        [$project, $workflowId] = $this->projectWithWorkflow();
        $stepId = $this->workflowStep($project->tenant_id, $workflowId, hasDueDate: true, isStart: true);

        ProjectWorkflowStep::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'workflow_step_id' => $stepId,
        ]);

        $this->assertNull($project->fresh()->start_date);
    }

    public function test_step_without_due_date_does_not_control_project_end(): void
    {
        [$project, $workflowId] = $this->projectWithWorkflow();
        $stepId = $this->workflowStep($project->tenant_id, $workflowId, hasDueDate: false, isEnd: true);

        $projectStep = ProjectWorkflowStep::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'workflow_step_id' => $stepId,
        ]);

        $this->assertFalse($projectStep->isScheduleStepForCurrentWorkflow());
        $this->assertSame('2026-10-31', $project->fresh()->end_date?->format('Y-m-d'));
    }

    private function projectWithWorkflow(): array
    {
        $tenant = Tenant::query()->firstOrFail();
        $workflowId = DB::table('workflows')->insertGetId([
            'tenant_id' => $tenant->id,
            'short_name' => 'TEST',
            'name' => 'Testworkflow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '260012',
            'title' => 'Testprojekt',
            'workflow_id' => $workflowId,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        return [$project, $workflowId];
    }

    private function workflowStep(int $tenantId, int $workflowId, bool $hasDueDate, bool $isStart = false, bool $isEnd = false): int
    {
        return DB::table('workflow_steps')->insertGetId([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflowId,
            'title' => 'Schritt',
            'has_due_date' => $hasDueDate,
            'is_start' => $isStart,
            'is_end' => $isEnd,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
