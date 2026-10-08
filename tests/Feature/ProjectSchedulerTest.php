<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use App\Services\ProjectScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Neues Terminmodell (docs/ablaufplan-konzept.md, Anhang A): Phasen, Phasenenden, Meilensteine, Fixpunkt. */
class ProjectSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    /** @var array<string, WorkflowStep> */
    private array $steps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'T', 'name' => 'Test', 'active' => true, 'sort' => 1]);
        // In Planung, drei Phasen (1, 2, 3 AT), Projektende
        $this->steps['plan'] = $this->step('In Planung', 1, 1, 'Projektstart');
        $this->steps['a'] = $this->step('A', 2, 2, '', 1);
        $this->steps['b'] = $this->step('B', 2, 3, 'B fertig', 2);
        $this->steps['c'] = $this->step('C', 2, 4, '', 3);
        $this->steps['end'] = $this->step('Projektende', 3, 5, '', 1);
    }

    private function step(string $title, int $lifecycle, int $sort, string $milestoneTitle = '', int $duration = 1): WorkflowStep
    {
        return WorkflowStep::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $sort,
            'lifecycle_status' => $lifecycle, 'duration_days' => $duration, 'milestone_title' => $milestoneTitle ?: null,
        ]);
    }

    private function project(string $start = '2027-03-01', int $model = 2): Project
    {
        return Project::query()->create([
            'tenant_id' => $this->tenant->id, 'source_pn' => '280'.random_int(100, 999), 'title' => 'P', 'status' => 0,
            'workflow_id' => $this->workflow->id, 'start_date' => $start, 'schedule_model' => $model,
        ]);
    }

    private function dueDate(Project $project, WorkflowStep $step): ?string
    {
        return ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $step->id)->value('due_date')?->toDateString()
            ?? null;
    }

    private function milestone(Project $project, array $attributes): ProjectMilestone
    {
        return ProjectMilestone::query()->create([...['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'name' => 'M', 'sort' => 1, 'offset_days' => 0], ...$attributes]);
    }

    public function test_phases_follow_each_other_without_gaps_and_write_the_phase_ends(): void
    {
        // Montag 1.3.2027: A = 1 AT (Mo), B = 2 AT (Di, Mi), C = 3 AT (Do, Fr, Mo)
        $project = $this->project();

        $this->assertSame('2027-03-01', $this->dueDate($project, $this->steps['a']));
        $this->assertSame('2027-03-03', $this->dueDate($project, $this->steps['b']));
        $this->assertSame('2027-03-08', $this->dueDate($project, $this->steps['c']));
        // Status-Schritte folgen dem Projekt
        $this->assertSame('2027-03-01', $this->dueDate($project, $this->steps['plan']));
        $this->assertSame('2027-03-08', $this->dueDate($project, $this->steps['end']));
        $this->assertSame('2027-03-08', $project->fresh()->end_date->toDateString());
    }

    public function test_start_on_weekend_rolls_forward_and_holidays_are_skipped(): void
    {
        Holiday::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Feiertag', 'date' => '2027-03-03', 'weekday' => 3, 'active' => true]);

        // Samstag 27.2. -> Montag 1.3.; B (Di 2.3., Mi 3.3. = Feiertag, Do 4.3.) endet am Donnerstag
        $project = $this->project('2027-02-27');

        $this->assertSame('2027-03-01', $this->dueDate($project, $this->steps['a']));
        $this->assertSame('2027-03-04', $this->dueDate($project, $this->steps['b']));
    }

    public function test_project_duration_override_counts_and_zero_counts_as_one(): void
    {
        $project = $this->project();
        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $this->steps['a']->id)->update(['duration_days' => 0]);
        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $this->steps['b']->id)->first()->update(['duration_days' => 5]);

        // A zählt 1 AT (Mo), B 5 AT (Di bis Mo)
        $this->assertSame('2027-03-01', $this->dueDate($project, $this->steps['a']));
        $this->assertSame('2027-03-08', $this->dueDate($project, $this->steps['b']));
    }

    public function test_steps_of_another_workflow_stay_untouched(): void
    {
        $other = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'O', 'name' => 'Anderer', 'active' => true, 'sort' => 2]);
        $foreign = WorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $other->id, 'title' => 'Fremd', 'sort' => 1, 'lifecycle_status' => 2, 'duration_days' => 9]);
        $project = $this->project();
        ProjectWorkflowStep::query()->withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $foreign->id, 'sort' => 1, 'due_date' => '2020-01-01']);

        app(ProjectScheduler::class)->recalculate($project->fresh());

        $this->assertSame('2020-01-01', $this->dueDate($project, $foreign));
        $this->assertSame('2027-03-08', $project->fresh()->end_date->toDateString());
    }

    public function test_all_anchor_types_including_before_start_and_after_end(): void
    {
        $project = $this->project();
        $step = ['anchor_workflow_step_id' => $this->steps['b']->id];

        $start = $this->milestone($project, ['anchor_type' => 'workflow_start', 'offset_days' => -2]);   // zwei Arbeitstage vor Start
        $end = $this->milestone($project, ['anchor_type' => 'workflow_end', 'offset_days' => 1]);        // einen nach Ende
        $stepStart = $this->milestone($project, ['anchor_type' => 'step_start', ...$step]);              // B beginnt Di 2.3.
        $stepEnd = $this->milestone($project, ['anchor_type' => 'step_end', ...$step, 'offset_days' => 1]);
        $fixed = $this->milestone($project, ['anchor_type' => 'fixed', 'fixed_date' => '2027-05-05']);

        $this->assertSame('2027-02-25', $start->fresh()->date->toDateString());
        $this->assertSame('2027-03-09', $end->fresh()->date->toDateString());
        $this->assertSame('2027-03-02', $stepStart->fresh()->date->toDateString());
        $this->assertSame('2027-03-04', $stepEnd->fresh()->date->toDateString());
        $this->assertSame('2027-05-05', $fixed->fresh()->date->toDateString());
    }

    public function test_milestone_without_reachable_step_stays_empty(): void
    {
        $project = $this->project();
        $milestone = $this->milestone($project, ['anchor_type' => 'step_end', 'anchor_workflow_step_id' => $this->steps['plan']->id]);

        $this->assertNull($milestone->fresh()->date);
    }

    public function test_template_milestones_are_copied_once_per_workflow(): void
    {
        WorkflowMilestone::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Markteinführung', 'sort' => 1,
            'anchor_type' => 'workflow_end', 'offset_days' => 1, 'is_market_launch' => true,
        ]);
        $project = $this->project();
        app(ProjectScheduler::class)->recalculate($project->fresh());

        $rows = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('2027-03-09', $rows->first()->date->toDateString());
        $this->assertTrue($rows->first()->is_market_launch);
    }

    public function test_fixed_point_returns_the_start_for_workflow_end_step_end_and_milestone(): void
    {
        $project = $this->project();
        $scheduler = app(ProjectScheduler::class);
        $milestone = $this->milestone($project, ['anchor_type' => 'workflow_end', 'offset_days' => 2]);

        // Workflow-Ende am Freitag 2.4.2027 -> der Projektstart wird so gesetzt, dass das Ende dort liegt
        $startForEnd = $scheduler->startDateFor($project, 'workflow_end', CarbonImmutable::parse('2027-04-02'));
        $project->update(['start_date' => $startForEnd->toDateString()]);
        $this->assertSame('2027-04-02', $project->fresh()->end_date->toDateString());

        // Ende von B auf Mittwoch 10.3.2027
        $startForStep = $scheduler->startDateFor($project->fresh(), 'step_end:'.$this->steps['b']->id, CarbonImmutable::parse('2027-03-10'));
        $project->update(['start_date' => $startForStep->toDateString()]);
        $this->assertSame('2027-03-10', $this->dueDate($project, $this->steps['b']));

        // Meilenstein (Workflow-Ende + 2 AT) auf Montag 15.3.2027
        $startForMilestone = $scheduler->startDateFor($project->fresh(), 'milestone:'.$milestone->id, CarbonImmutable::parse('2027-03-15'));
        $project->update(['start_date' => $startForMilestone->toDateString()]);
        $this->assertSame('2027-03-15', $milestone->fresh()->date->toDateString());
    }

    public function test_fixed_dated_milestone_cannot_be_a_fixed_point(): void
    {
        $project = $this->project();
        $milestone = $this->milestone($project, ['anchor_type' => 'fixed', 'fixed_date' => '2027-05-05']);

        $this->expectException(\InvalidArgumentException::class);
        app(ProjectScheduler::class)->startDateFor($project, 'milestone:'.$milestone->id, CarbonImmutable::parse('2027-05-05'));
    }

    public function test_triggers_recalculate_on_start_duration_and_holiday_changes(): void
    {
        $project = $this->project();
        $this->assertSame('2027-03-08', $this->dueDate($project, $this->steps['c']));

        $project->update(['start_date' => '2027-03-08']);
        $this->assertSame('2027-03-15', $this->dueDate($project, $this->steps['c']));

        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $this->steps['c']->id)->first()->update(['duration_days' => 4]);
        $this->assertSame('2027-03-16', $this->dueDate($project, $this->steps['c']));

        Holiday::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Feiertag', 'date' => '2027-03-16', 'weekday' => 2, 'active' => true]);
        $this->assertSame('2027-03-17', $this->dueDate($project, $this->steps['c']));
    }

    public function test_old_model_projects_keep_their_manual_dates(): void
    {
        $project = $this->project(model: 1);
        ProjectWorkflowStep::query()->withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $this->steps['b']->id, 'sort' => 3, 'due_date' => '2030-01-01']);

        $project->update(['start_date' => '2027-04-01']);
        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->first()->update(['duration_days' => 7]);

        $this->assertSame('2030-01-01', $this->dueDate($project, $this->steps['b']));
    }

    public function test_without_start_or_workflow_nothing_is_written(): void
    {
        $project = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '280001', 'title' => 'P', 'status' => 0, 'schedule_model' => 2, 'workflow_id' => $this->workflow->id]);

        $this->assertSame(0, ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->count());
    }

    public function test_set_period_end_moves_the_start_and_durations_save_recalculates(): void
    {
        $project = $this->project();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);

        // gewünschtes Ende Freitag 2.4.2027 -> Start so, dass die Kette dort endet
        $this->actingAs($admin)->postJson(route('projekte.termine.set-period', $project), ['side' => 'end', 'date' => '2027-04-02'])->assertOk();
        $this->assertSame('2027-04-02', $project->fresh()->end_date->toDateString());

        // Dauer von C auf 5 AT: das Ende rückt um zwei Arbeitstage nach hinten
        $this->actingAs($admin)->postJson(route('projekte.termine.save-durations', $project), ['durations' => [$this->steps['c']->id => 5]])->assertOk();
        $this->assertSame('2027-04-06', $project->fresh()->end_date->toDateString());
    }

    public function test_manual_due_date_is_ignored_in_the_new_model(): void
    {
        $project = $this->project();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $row = ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $this->steps['b']->id)->first();

        $this->actingAs($admin)->patchJson(route('projekte.termine.update-field', [$project, $row]), ['due_date' => '2030-01-01'])->assertOk();

        $this->assertSame('2027-03-03', $this->dueDate($project, $this->steps['b']));
    }

    public function test_milestones_can_be_created_changed_and_deleted_in_the_project(): void
    {
        $project = $this->project();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);

        // anlegen: zwei Arbeitstage nach dem Ende von B (B endet Mi 3.3.)
        $this->actingAs($admin)->postJson(route('projekte.meilensteine.store', $project), [
            'name' => 'Abnahme', 'anchor_type' => 'step_end', 'anchor_workflow_step_id' => $this->steps['b']->id, 'offset_days' => 2,
        ])->assertOk();
        $milestone = ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole();
        $this->assertSame('2027-03-05', $milestone->date->toDateString());

        // ändern: festes Datum
        $this->actingAs($admin)->patchJson(route('projekte.meilensteine.update', [$project, $milestone]), ['name' => 'Messe', 'anchor_type' => 'fixed', 'fixed_date' => '2027-09-01'])->assertOk();
        $milestone->refresh();
        $this->assertSame('Messe', $milestone->name);
        $this->assertSame('2027-09-01', $milestone->date->toDateString());
        $this->assertNull($milestone->anchor_workflow_step_id);

        // löschen
        $this->actingAs($admin)->deleteJson(route('projekte.meilensteine.destroy', [$project, $milestone]))->assertOk();
        $this->assertSame(0, ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->count());
    }

    public function test_milestone_input_is_validated(): void
    {
        $project = $this->project();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $foreign = WorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'X', 'name' => 'X', 'active' => true, 'sort' => 9])->id, 'title' => 'Fremd', 'sort' => 1, 'lifecycle_status' => 2, 'duration_days' => 1]);

        $this->actingAs($admin)->postJson(route('projekte.meilensteine.store', $project), ['name' => '', 'anchor_type' => 'workflow_end'])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('projekte.meilensteine.store', $project), ['name' => 'X', 'anchor_type' => 'step_end', 'anchor_workflow_step_id' => $foreign->id])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('projekte.meilensteine.store', $project), ['name' => 'X', 'anchor_type' => 'fixed'])->assertStatus(422);
        $this->assertSame(0, ProjectMilestone::query()->withoutGlobalScopes()->where('project_id', $project->id)->count());
    }

    public function test_milestones_need_the_right_and_the_new_model(): void
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
        $project = $this->project();
        $this->actingAs($user)->postJson(route('projekte.meilensteine.store', $project), ['name' => 'X', 'anchor_type' => 'workflow_end'])->assertForbidden();

        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $old = $this->project(model: 1);
        $this->actingAs($admin)->postJson(route('projekte.meilensteine.store', $old), ['name' => 'X', 'anchor_type' => 'workflow_end'])->assertStatus(422);
    }
}
