<?php

namespace Tests\Feature;

use App\Models\MailTemplate;
use App\Models\MailTimer;
use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Workflow-Wechsel: die Schritte des alten Workflows gehören nicht mehr zum Projekt (Ralf, 2026-10-09). */
class WorkflowSwitchPurgeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $old;

    private Workflow $new;

    /** @var array<string, WorkflowStep> */
    private array $steps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->old = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'A', 'name' => 'Alt', 'active' => true, 'sort' => 1]);
        $this->new = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'B', 'name' => 'Neu', 'active' => true, 'sort' => 2]);
        $this->steps['old1'] = $this->step($this->old, 'Alt 1', 1);
        $this->steps['old2'] = $this->step($this->old, 'Alt 2', 2);
        $this->steps['new1'] = $this->step($this->new, 'Neu 1', 1);
    }

    private function step(Workflow $workflow, string $title, int $sort): WorkflowStep
    {
        return WorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $workflow->id, 'title' => $title, 'sort' => $sort, 'lifecycle_status' => 2, 'duration_days' => 2]);
    }

    private function project(): Project
    {
        $project = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '270'.random_int(100, 999), 'title' => 'Wechsel', 'status' => 1, 'workflow_id' => $this->old->id]);
        foreach (['old1', 'old2'] as $key) {
            ProjectWorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $this->steps[$key]->id, 'sort' => 1]);
        }

        return $project;
    }

    private function timer(Project $project, WorkflowStep $step, array $attributes = []): MailTimer
    {
        $template = MailTemplate::query()->firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Vorlage'], ['subject' => 'x', 'body' => 'y']);

        return MailTimer::query()->create([...[
            'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $step->id, 'mail_template_id' => $template->id,
            'reference_type' => 'fixed', 'fixed_date' => '2027-03-01', 'send_date' => '2027-03-01', 'offset_days' => 0, 'only_if_in_step' => true, 'function_group_ids' => [],
        ], ...$attributes]);
    }

    private function stepIds(Project $project): array
    {
        return ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->pluck('workflow_step_id')->all();
    }

    public function test_switching_the_workflow_removes_the_steps_of_the_old_workflow_and_keeps_the_new_ones(): void
    {
        $project = $this->project();
        ProjectWorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $this->steps['new1']->id, 'sort' => 1]);
        $this->assertCount(3, $this->stepIds($project));

        $project->update(['workflow_id' => $this->new->id]);

        $this->assertSame([$this->steps['new1']->id], $this->stepIds($project));
    }

    public function test_open_reminders_of_the_old_workflow_go_but_sent_ones_stay_as_history(): void
    {
        $project = $this->project();
        $open = $this->timer($project, $this->steps['old1']);
        $sent = $this->timer($project, $this->steps['old2'], ['sent_at' => now()]);
        $kept = $this->timer($project, $this->steps['new1']);

        $project->update(['workflow_id' => $this->new->id]);

        $this->assertModelMissing($open);
        $this->assertModelExists($sent);
        $this->assertModelExists($kept);
    }

    public function test_without_a_workflow_no_step_rows_remain(): void
    {
        $project = $this->project();

        $project->update(['workflow_id' => null]);

        $this->assertSame([], $this->stepIds($project));
    }

    public function test_saving_without_a_workflow_change_touches_nothing(): void
    {
        $project = $this->project();

        $project->update(['title' => 'Nur ein neuer Titel']);

        $this->assertCount(2, $this->stepIds($project));
    }

    public function test_the_cleanup_command_handles_existing_leftovers_and_has_a_dry_run(): void
    {
        $project = $this->project();
        // Altlast: Workflow ohne Beobachter umgehängt (so entstanden die vorhandenen Fälle)
        \DB::table('projects')->where('id', $project->id)->update(['workflow_id' => $this->new->id]);
        $this->assertCount(2, $this->stepIds($project));

        $this->artisan('projects:purge-foreign-workflow-steps', ['--dry-run' => true])->expectsOutputToContain('Würde entfernen: 2 Schritt-Zeilen in 1 Projekten.')->assertSuccessful();
        $this->assertCount(2, $this->stepIds($project));

        $this->artisan('projects:purge-foreign-workflow-steps')->expectsOutputToContain('Entfernt: 2 Schritt-Zeilen in 1 Projekten.')->assertSuccessful();
        $this->assertSame([], $this->stepIds($project));
    }
}
