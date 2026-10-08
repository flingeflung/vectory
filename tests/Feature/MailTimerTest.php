<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\MailTemplate;
use App\Models\MailTimer;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowMailTimer;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use App\Services\MailTemplateRenderer;
use App\Services\MailTimerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Mail-Timer: Erinnerungsmails aus Mail-Vorlagen, Sendedatum nach Meilenstein/Phasenende (Ralf, 2026-10-09). */
class MailTimerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    /** @var array<string, WorkflowStep> */
    private array $steps = [];

    private MailTemplate $template;

    private FunctionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'T', 'name' => 'Test', 'active' => true, 'sort' => 1]);
        $this->steps['plan'] = $this->step('In Planung', 1, 1, 1);
        $this->steps['a'] = $this->step('A', 2, 2, 2);
        $this->steps['b'] = $this->step('B', 2, 3, 3, 'B fertig');
        $this->steps['end'] = $this->step('Ende', 3, 4, 1);
        $this->template = MailTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Erinnerung', 'subject' => 'Bitte prüfen: {pn}', 'body' => "Projekt {pn} {title}\nStart {start_date}"]);
        $this->group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'Redaktion', 'short_name' => 'RED', 'sort' => 1, 'active' => true]);
    }

    private function step(string $title, int $lifecycle, int $sort, int $days, string $milestoneTitle = ''): WorkflowStep
    {
        return WorkflowStep::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $sort,
            'lifecycle_status' => $lifecycle, 'duration_days' => $days, 'milestone_title' => $milestoneTitle ?: null, 'has_due_date' => $milestoneTitle !== '',
        ]);
    }

    private function project(): Project
    {
        // Mo 1.3.2027: A = Mo-Di (2 AT), B = Mi-Fr (3 AT) -> B endet Fr 5.3.
        return Project::query()->create([
            'tenant_id' => $this->tenant->id, 'source_pn' => '290'.random_int(100, 999), 'title' => 'Titel', 'status' => 0,
            'workflow_id' => $this->workflow->id, 'start_date' => '2027-03-01', 'schedule_model' => 2,
        ]);
    }

    private function staff(Project $project, string $email = 'red@example.test', bool $active = true): Person
    {
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Redakteur', 'email' => $email, 'active' => $active]);
        ProjectPerson::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'function_group_id' => $this->group->id, 'person_id' => $person->id]);

        return $person;
    }

    private function timer(Project $project, array $attributes = []): MailTimer
    {
        return MailTimer::query()->create([...[
            'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $this->template->id,
            'reference_type' => 'phase_end', 'reference_step_id' => $this->steps['b']->id, 'offset_days' => -2, 'only_if_in_step' => true,
            'function_group_ids' => [$this->group->id],
        ], ...$attributes]);
    }

    private function setCurrent(Project $project, WorkflowStep $step): void
    {
        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->update(['is_current' => false]);
        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $step->id)->update(['is_current' => true]);
    }

    public function test_placeholders_are_filled_from_the_project(): void
    {
        $project = $this->project();
        $rendered = app(MailTemplateRenderer::class)->render($this->template, $project->fresh());

        $this->assertSame('Bitte prüfen: '.$project->source_pn, $rendered['subject']);
        $this->assertSame("Projekt {$project->source_pn} Titel\nStart 01.03.2027", $rendered['body']);
    }

    public function test_send_date_follows_phase_end_milestone_and_fixed_date(): void
    {
        $project = $this->project();   // B endet Fr 5.3.
        $service = app(MailTimerService::class);

        $byPhase = $this->timer($project);
        $this->assertSame('2027-03-03', $service->sendDateFor($byPhase)->toDateString());

        $milestone = ProjectMilestone::query()->withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'name' => 'M', 'anchor_type' => 'workflow_end', 'offset_days' => 0]);
        $byMilestone = $this->timer($project, ['reference_type' => 'milestone', 'reference_milestone_id' => $milestone->id, 'reference_step_id' => null, 'offset_days' => -49]);
        $this->assertSame('2027-01-15', $service->sendDateFor($byMilestone)->toDateString());   // 5.3. minus 7 Wochen

        $fixed = $this->timer($project, ['reference_type' => 'fixed', 'fixed_date' => '2027-05-05', 'reference_step_id' => null, 'offset_days' => 99]);
        $this->assertSame('2027-05-05', $service->sendDateFor($fixed)->toDateString());

        // Termin verschiebt sich -> Sendedatum folgt
        $project->update(['start_date' => '2027-03-08']);
        $this->assertSame('2027-03-10', $service->sendDateFor($byPhase->fresh())->toDateString());
    }

    public function test_workflow_templates_are_copied_to_projects_without_duplicates(): void
    {
        $workflowMilestone = WorkflowMilestone::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Messe', 'anchor_type' => 'workflow_end', 'offset_days' => 1]);
        WorkflowMailTimer::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $this->template->id,
            'reference_type' => 'milestone', 'reference_milestone_id' => $workflowMilestone->id, 'offset_days' => -49, 'only_if_in_step' => true, 'function_group_ids' => [$this->group->id],
        ]);

        $project = $this->project();
        app(\App\Services\ProjectScheduler::class)->recalculate($project->fresh());
        app(\App\Services\ProjectScheduler::class)->recalculate($project->fresh());

        $timer = MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole();
        $this->assertNotNull($timer->workflow_mail_timer_id);
        // Meilenstein = Workflow-Ende (Fr 5.3.) + 1 AT = Mo 8.3.; 49 Tage davor = 18.1.
        $this->assertSame('2027-01-18', $timer->send_date->toDateString());
    }

    public function test_due_timer_is_sent_when_the_project_is_in_the_step(): void
    {
        $project = $this->project();
        $this->staff($project);
        $this->staff($project, 'inaktiv@example.test', false);
        $timer = $this->timer($project);
        $this->setCurrent($project, $this->steps['b']);
        $service = app(MailTimerService::class);
        $service->refreshSendDates($project);

        $result = $service->sendDue(CarbonImmutable::parse('2027-03-03'));

        $this->assertSame(['sent' => 1, 'skipped' => 0, 'failed' => 0, 'waiting' => 0], $result);
        $this->assertNotNull($timer->fresh()->sent_at);
        $this->assertSame(['red@example.test'], $service->recipients($timer->fresh()));
        $this->assertDatabaseHas('activities', ['project_id' => $project->id, 'type' => 'mail_timer_sent']);
        // ein zweiter Lauf sendet nichts mehr
        $this->assertSame(0, $service->sendDue(CarbonImmutable::parse('2027-03-04'))['sent']);
    }

    public function test_timer_waits_before_the_step_and_is_skipped_after_it(): void
    {
        $project = $this->project();
        $this->staff($project);
        $timer = $this->timer($project);
        $service = app(MailTimerService::class);
        $service->refreshSendDates($project);

        $this->setCurrent($project, $this->steps['a']);
        $this->assertSame(1, $service->sendDue(CarbonImmutable::parse('2027-03-03'))['waiting']);
        $this->assertTrue($timer->fresh()->isPending());

        $this->setCurrent($project, $this->steps['end']);
        $this->assertSame(1, $service->sendDue(CarbonImmutable::parse('2027-03-03'))['skipped']);
        $this->assertNotNull($timer->fresh()->skipped_at);
        $this->assertNull($timer->fresh()->sent_at);
    }

    public function test_without_the_step_condition_the_mail_goes_out_anyway(): void
    {
        $project = $this->project();
        $this->staff($project);
        $timer = $this->timer($project, ['only_if_in_step' => false]);
        $this->setCurrent($project, $this->steps['a']);
        $service = app(MailTimerService::class);
        $service->refreshSendDates($project);

        $this->assertSame(1, $service->sendDue(CarbonImmutable::parse('2027-03-03'))['sent']);
    }

    public function test_missing_recipients_are_reported_and_the_timer_stays_open(): void
    {
        $project = $this->project();
        $timer = $this->timer($project);
        $this->setCurrent($project, $this->steps['b']);
        $service = app(MailTimerService::class);
        $service->refreshSendDates($project);

        $result = $service->sendDue(CarbonImmutable::parse('2027-03-03'));

        $this->assertSame(1, $result['failed']);
        $this->assertTrue($timer->fresh()->isPending());
        $this->assertStringContainsString('Keine Empfänger', (string) $timer->fresh()->last_error);
    }

    public function test_finished_projects_do_not_get_reminders(): void
    {
        $project = $this->project();
        $this->staff($project);
        $timer = $this->timer($project);
        $service = app(MailTimerService::class);
        $service->refreshSendDates($project);
        $project->update(['status' => 3]);

        $this->assertSame(1, $service->sendDue(CarbonImmutable::parse('2027-03-03'))['skipped']);
        $this->assertNotNull($timer->fresh()->skipped_at);
    }
}
