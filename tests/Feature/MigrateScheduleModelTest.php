<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Umstellung bestehender Workflows und Projekte auf das neue Terminmodell (docs/ablaufplan-konzept.md, A.6). */
class MigrateScheduleModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    private Project $project;

    /** @var array<string, WorkflowStep> */
    private array $steps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'P', 'name' => 'Print', 'active' => true, 'sort' => 1]);

        $make = fn (string $key, string $title, int $sort, int $lifecycle, int $duration, array $extra = []) => $this->steps[$key] = WorkflowStep::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $sort,
            'lifecycle_status' => $lifecycle, 'duration_days' => $duration, ...$extra,
        ]);
        $make('plan', 'In Planung', 1, 1, 1, ['has_due_date' => true, 'is_start' => true, 'milestone_title' => 'Projektstart']);
        $make('work', 'Anleitung', 2, 2, 3, ['milestone_title' => 'Anleitung fertig']);
        $make('print', 'Druck', 3, 2, 2, ['is_active' => false, 'has_due_date' => true, 'milestone_title' => 'Fertig gedruckt']);
        $make('launch', 'Markteinführung', 4, 2, 1, ['is_active' => false, 'has_due_date' => true, 'is_market_launch' => true, 'milestone_title' => 'Markteinführung']);
        $make('end', 'Projektende', 5, 3, 1, ['has_due_date' => true, 'is_end' => true]);

        $this->project = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '290001', 'title' => 'P', 'status' => 0, 'workflow_id' => $this->workflow->id]);
        foreach ($this->steps as $step) {
            ProjectWorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'workflow_step_id' => $step->id, 'sort' => $step->sort]);
        }
        ProjectWorkflowStep::query()->where('project_id', $this->project->id)->where('workflow_step_id', $this->steps['plan']->id)->update(['due_date' => '2027-03-01']);
        ProjectWorkflowStep::query()->where('project_id', $this->project->id)->where('workflow_step_id', $this->steps['launch']->id)->update(['due_date' => '2027-06-01']);
    }

    private function migrate(string ...$options): void
    {
        $this->artisan('schedule:migrate-v2', ['--tenant' => [$this->tenant->id], ...array_fill_keys($options, true)])->assertSuccessful();
    }

    public function test_workflow_and_project_are_converted(): void
    {
        $this->migrate();

        // Markteinführung wird Meilenstein mit Kennzeichnung, der alte Schritt verschwindet
        $this->assertNull(WorkflowStep::query()->withoutGlobalScopes()->find($this->steps['launch']->id));
        $template = WorkflowMilestone::query()->withoutGlobalScopes()->where('workflow_id', $this->workflow->id)->sole();
        $this->assertSame('Markteinführung', $template->name);
        $this->assertTrue($template->is_market_launch);
        $this->assertSame('workflow_end', $template->anchor_type);

        // Druck wird normale Phase, Termin-Kennzeichen aus Namen und Status
        $print = $this->steps['print']->fresh();
        $this->assertTrue($print->is_active);
        $this->assertTrue($print->has_due_date);
        $this->assertFalse($this->steps['work']->fresh()->is_start);
        $this->assertTrue($this->steps['plan']->fresh()->is_start);
        $this->assertTrue($this->steps['end']->fresh()->is_end);

        // Projekt: neues Modell, der Meilenstein folgt dem Workflow-Ende (+1 AT) statt dem alten Datum
        $project = $this->project->fresh();
        $this->assertSame(2, (int) $project->schedule_model);
        $this->assertSame('2027-03-01', $project->start_date->toDateString());
        $milestone = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole();
        $this->assertSame('workflow_end', $milestone->anchor_type);
        $this->assertSame('2027-03-08', $milestone->date->toDateString());

        // Phasenenden berechnet: Anleitung 3 AT Mo-Mi, Druck 2 AT Do-Fr
        $due = fn (string $key) => ProjectWorkflowStep::query()->where('project_id', $project->id)->where('workflow_step_id', $this->steps[$key]->id)->value('due_date');
        $this->assertSame('2027-03-03', $due('work')->toDateString());
        $this->assertSame('2027-03-05', $due('print')->toDateString());
        $this->assertSame('2027-03-05', $project->end_date->toDateString());
    }

    public function test_second_run_changes_nothing_and_dry_run_writes_nothing(): void
    {
        $this->migrate('--dry-run');
        $this->assertSame(1, (int) $this->project->fresh()->schedule_model);
        $this->assertNotNull(WorkflowStep::query()->withoutGlobalScopes()->find($this->steps['launch']->id));

        $this->migrate();
        $this->migrate();

        $this->assertSame(1, WorkflowMilestone::query()->withoutGlobalScopes()->where('workflow_id', $this->workflow->id)->count());
        $this->assertSame(1, ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $this->project->id)->count());
    }

    public function test_project_without_start_uses_the_planning_step_date(): void
    {
        $this->project->update(['start_date' => null]);
        $this->migrate();

        $this->assertSame('2027-03-01', $this->project->fresh()->start_date->toDateString());
    }
}
