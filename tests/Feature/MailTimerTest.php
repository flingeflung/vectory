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

    public function test_timers_can_be_listed_created_and_deleted_in_the_project(): void
    {
        $project = $this->project();
        $this->staff($project);
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);

        $this->actingAs($admin)->get(route('projekte.mailtimer.index', $project))->assertOk()->assertSee('noch keine Erinnerungen');

        $this->actingAs($admin)->postJson(route('projekte.mailtimer.store', $project), [
            'workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $this->template->id, 'reference' => 'phase_end:'.$this->steps['b']->id,
            'offset_days' => -14, 'function_group_ids' => [$this->group->id], 'only_if_in_step' => true,
        ])->assertOk();
        $timer = MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole();
        $this->assertSame('2027-02-19', $timer->send_date->toDateString());   // B endet Fr 5.3., 14 Tage davor
        $this->assertSame($admin->id, $timer->created_by_user_id);

        $this->actingAs($admin)->get(route('projekte.mailtimer.index', $project))->assertOk()
            ->assertSee('19.02.2027')->assertSee('2 Wochen vor Ende von');

        $this->actingAs($admin)->deleteJson(route('projekte.mailtimer.destroy', [$project, $timer]))->assertOk();
        $this->assertSame(0, MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->count());
    }

    public function test_creating_a_timer_validates_input_and_needs_the_right(): void
    {
        $project = $this->project();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $url = route('projekte.mailtimer.store', $project);
        $base = ['workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $this->template->id, 'reference' => 'fixed', 'fixed_date' => '2027-05-01', 'function_group_ids' => [$this->group->id]];

        $this->actingAs($admin)->postJson($url, [...$base, 'function_group_ids' => []])->assertStatus(422);
        $this->actingAs($admin)->postJson($url, [...$base, 'fixed_date' => null])->assertStatus(422);
        $this->actingAs($admin)->postJson($url, [...$base, 'reference' => 'milestone:999999'])->assertStatus(422);
        $this->actingAs($admin)->postJson($url, [...$base, 'mail_template_id' => 999999])->assertStatus(422);
        $this->actingAs($admin)->postJson($url, [...$base, 'workflow_step_id' => 999999])->assertStatus(422);
        $this->assertSame(0, MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->count());

        $this->actingAs($admin)->postJson($url, $base)->assertOk();
        $this->assertSame('2027-05-01', MailTimer::query()->withoutGlobalScopes()->where('project_id', $project->id)->sole()->send_date->toDateString());

        $user = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
        $this->actingAs($user)->postJson($url, $base)->assertForbidden();
    }

    public function test_project_page_shows_the_conspicuous_button_only_with_timers(): void
    {
        $project = $this->project();
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);

        $this->actingAs($admin)->get(route('projekte.show', $project), ['X-Overlay' => '1'])->assertOk()->assertDontSee('window.openMailTimers('.$project->id.')', false);

        $this->timer($project);
        $this->actingAs($admin)->get(route('projekte.show', $project), ['X-Overlay' => '1'])->assertOk()->assertSee('window.openMailTimers('.$project->id.')', false);
    }

    public function test_workflow_admin_manages_standard_reminders_and_freezes_them_when_published(): void
    {
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($admin);
        $milestone = WorkflowMilestone::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Messe', 'anchor_type' => 'workflow_end', 'offset_days' => 1]);
        $payload = ['workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $this->template->id, 'reference' => 'milestone:'.$milestone->id, 'offset_days' => -49, 'function_group_ids' => [$this->group->id], 'only_if_in_step' => 1];

        $this->post(route('admin.workflows.mailtimers.store', $this->workflow), $payload)->assertRedirect();
        $row = WorkflowMailTimer::query()->where('workflow_id', $this->workflow->id)->sole();
        $this->assertSame('milestone', $row->reference_type);
        $this->assertSame($milestone->id, $row->reference_milestone_id);
        $this->assertSame(-49, $row->offset_days);

        $this->patch(route('admin.workflows.mailtimers.update', [$this->workflow, $row]), [...$payload, 'reference' => 'phase_end:'.$this->steps['b']->id, 'offset_days' => -7])->assertRedirect();
        $row->refresh();
        $this->assertSame('phase_end', $row->reference_type);
        $this->assertNull($row->reference_milestone_id);
        $this->assertSame($this->steps['b']->id, $row->reference_step_id);

        $this->get(route('admin.workflows', ['workflow' => $this->workflow->id]))->assertOk()->assertSee('Erinnerungen per Mail')->assertSee('1 Woche vor Ende von');

        $this->post(route('admin.workflows.mailtimers.store', $this->workflow), [...$payload, 'function_group_ids' => []])->assertSessionHasErrors();
        $this->delete(route('admin.workflows.mailtimers.destroy', [$this->workflow, $row]))->assertRedirect();
        $this->assertSame(0, WorkflowMailTimer::query()->where('workflow_id', $this->workflow->id)->count());

        $this->workflow->update(['published_at' => now()]);
        $this->postJson(route('admin.workflows.mailtimers.store', $this->workflow), $payload)->assertStatus(422);
    }

    public function test_new_version_takes_standard_reminders_along_with_translated_references(): void
    {
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($admin);
        $milestone = WorkflowMilestone::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Messe', 'anchor_type' => 'workflow_end', 'offset_days' => 1]);
        WorkflowMailTimer::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $this->template->id,
            'reference_type' => 'milestone', 'reference_milestone_id' => $milestone->id, 'offset_days' => -49, 'only_if_in_step' => true, 'function_group_ids' => [$this->group->id],
        ]);

        $this->workflow->update(['published_at' => now()]);
        $this->post(route('admin.workflows.new-version', $this->workflow))->assertRedirect();

        $copy = Workflow::query()->where('tenant_id', $this->tenant->id)->where('id', '!=', $this->workflow->id)->sole();
        $copied = WorkflowMailTimer::query()->where('workflow_id', $copy->id)->sole();
        $newStep = WorkflowStep::query()->where('workflow_id', $copy->id)->where('title', 'B')->sole();
        $newMilestone = WorkflowMilestone::query()->where('workflow_id', $copy->id)->sole();
        $this->assertSame($newStep->id, $copied->workflow_step_id);
        $this->assertSame($newMilestone->id, $copied->reference_milestone_id);
        $this->assertSame($this->template->id, $copied->mail_template_id);
        $this->assertSame([$this->group->id], $copied->function_group_ids);
    }

    public function test_template_description_is_saved_shown_in_the_dialog_and_never_sent(): void
    {
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($admin);

        $this->post(route('admin.mail-vorlagen.update', $this->template), [
            'name' => 'Erinnerung', 'description' => 'Erinnert die Redaktion an die Freigabe.', 'subject' => 'Betreff {pn}', 'body' => 'Text',
        ])->assertRedirect();
        $this->assertSame('Erinnert die Redaktion an die Freigabe.', $this->template->fresh()->description);

        $project = $this->project();
        $this->get(route('projekte.mailtimer.index', $project))->assertOk()->assertSee('Erinnert die Redaktion an die Freigabe.');

        $rendered = app(MailTemplateRenderer::class)->render($this->template->fresh(), $project->fresh());
        $this->assertStringNotContainsString('Redaktion', $rendered['subject'].$rendered['body']);
    }

    public function test_step_placeholders_exist_only_in_a_step_context(): void
    {
        $project = $this->project();
        $project->refresh();
        ProjectWorkflowStep::query()->withoutGlobalScopes()->where('project_id', $project->id)->where('workflow_step_id', $this->steps['b']->id)->update(['started_at' => '2027-03-02 08:00:00']);
        $template = MailTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Schritt', 'subject' => 'Schritt {wfs}', 'body' => 'seit {wfs_seit}, Ende {wfs_ende}']);
        $renderer = app(MailTemplateRenderer::class);

        $rendered = $renderer->render($template, $project->fresh(), $this->steps['b']->id);
        $this->assertSame('Schritt B', $rendered['subject']);
        $this->assertSame('seit 02.03.2027, Ende 05.03.2027', $rendered['body']);

        // ohne Schritt-Zusammenhang sind die Felder nicht verfügbar; bekannte Projektfelder dagegen schon
        $this->assertSame(['{wfs}', '{wfs_seit}', '{wfs_ende}'], $renderer->unavailable($template, $this->tenant->id, false));
        $this->assertSame([], $renderer->unavailable($template, $this->tenant->id, true));
        $this->assertSame([], $renderer->unavailable($this->template, $this->tenant->id, false));
    }

    public function test_unknown_fields_block_saving_a_timer_and_sending_the_mail(): void
    {
        $project = $this->project();
        $this->staff($project);
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $broken = MailTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kaputt', 'subject' => 'Hallo {pn}', 'body' => 'Text mit {tippfehler}']);

        // Anlegen im Projekt wird abgelehnt
        $this->actingAs($admin)->postJson(route('projekte.mailtimer.store', $project), [
            'workflow_step_id' => $this->steps['b']->id, 'mail_template_id' => $broken->id, 'reference' => 'fixed', 'fixed_date' => '2027-05-01', 'function_group_ids' => [$this->group->id],
        ])->assertStatus(422)->assertJsonFragment(['message' => 'Diese Vorlage enthält Felder, die hier nicht zur Verfügung stehen: {tippfehler}.']);

        // Nachträglich kaputt gemachte Vorlage: nicht senden, Fehler zeigen, Erinnerung bleibt offen
        $timer = $this->timer($project);
        $this->setCurrent($project, $this->steps['b']);
        $service = app(MailTimerService::class);
        $service->refreshSendDates($project);
        $this->template->update(['body' => 'Text mit {tippfehler}']);

        $result = $service->sendDue(CarbonImmutable::parse('2027-03-03'));

        $this->assertSame(1, $result['failed']);
        $this->assertTrue($timer->fresh()->isPending());
        $this->assertStringContainsString('{tippfehler}', (string) $timer->fresh()->last_error);
        $this->assertNull($timer->fresh()->sent_at);

        // Vorlage repariert -> beim nächsten Lauf geht die Mail raus
        $this->template->update(['body' => 'Text {wfs}']);
        $this->assertSame(1, $service->sendDue(CarbonImmutable::parse('2027-03-04'))['sent']);
    }

    public function test_template_page_offers_step_fields_and_warns_about_unknown_ones(): void
    {
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($admin);
        $this->template->update(['body' => 'Hallo {pn} {tippfehler}']);

        $this->get(route('admin.mail-vorlagen', ['template' => $this->template->id]))->assertOk()
            ->assertSee('Nur bei Mails zu einem Workflow-Schritt')->assertSee('Schritt aktiv seit')->assertSee('Unbekannte Felder in dieser Vorlage: {tippfehler}');
    }
}
