<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\MailTemplate;
use App\Models\MailTimer;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Planung › Erinnerungen: alle Mail-Timer der Organisation auf einen Blick (Ralf, 2026-10-09). */
class PlanningMailTimerPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private WorkflowStep $step;

    private MailTemplate $template;

    private FunctionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'T', 'name' => 'Test', 'active' => true, 'sort' => 1]);
        $this->step = WorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $workflow->id, 'title' => 'Korrekturlesen', 'sort' => 1, 'lifecycle_status' => 2, 'duration_days' => 3]);
        $this->template = MailTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Erinnerung Korrektur', 'subject' => 'Bitte prüfen: {pn}', 'body' => 'Projekt {pn}']);
        $this->group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'Redaktion', 'short_name' => 'RED', 'sort' => 1, 'active' => true]);
    }

    private function project(string $pn, int $status = 1, bool $archived = false): Project
    {
        return Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => $pn, 'title' => 'Titel '.$pn, 'status' => $status, 'archived' => $archived, 'schedule_model' => 2]);
    }

    private function timer(Project $project, array $attributes = []): MailTimer
    {
        return MailTimer::query()->create([...[
            'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $this->step->id, 'mail_template_id' => $this->template->id,
            'reference_type' => 'fixed', 'fixed_date' => '2026-11-15', 'send_date' => '2026-11-15', 'offset_days' => 0, 'only_if_in_step' => true,
            'function_group_ids' => [$this->group->id],
        ], ...$attributes]);
    }

    private function userWith(array $keys): User
    {
        $set = PermissionTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Set '.random_int(1000, 9999)]);
        $set->permissions()->attach(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $person = Person::query()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'P'.random_int(1000, 9999), 'active' => true, 'permission_template_id' => $set->id]);

        return User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'role' => 'user']);
    }

    private function admin(): User
    {
        return User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
    }

    public function test_open_timers_are_listed_sorted_by_send_date_with_project_template_rule_and_recipients(): void
    {
        $this->timer($this->project('260901'), ['send_date' => '2026-12-01', 'fixed_date' => '2026-12-01']);
        $this->timer($this->project('260902'), ['send_date' => '2026-11-15']);
        $this->timer($this->project('260903'), ['send_date' => null, 'reference_type' => 'phase_end', 'reference_step_id' => $this->step->id, 'offset_days' => -7]);

        $response = $this->actingAs($this->admin())->get(route('planung.erinnerungen'))->assertOk()
            ->assertSee('Erinnerung Korrektur')->assertSee('Korrekturlesen')->assertSee('Redaktion')->assertSee('Titel 260901')
            ->assertSee('1 Woche vor Ende von „Korrekturlesen“', false)->assertSee('wartet auf Termin')->assertSee('festes Datum');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, '260901'), strpos($html, '260902'));
        $this->assertLessThan(strpos($html, '260903'), strpos($html, '260901'));
    }

    public function test_finished_projects_and_done_timers_are_hidden_by_default_and_shown_on_request(): void
    {
        $open = $this->project('260911');
        $ended = $this->project('260912', 2);
        $archived = $this->project('260913', 1, true);
        $sent = $this->project('260914');
        $this->timer($open);
        $this->timer($ended);
        $this->timer($archived);
        $this->timer($sent, ['sent_at' => now()]);

        $this->actingAs($this->admin())->get(route('planung.erinnerungen'))->assertOk()
            ->assertSee('260911')->assertDontSee('260912')->assertDontSee('260913')->assertDontSee('260914');
        $this->actingAs($this->admin())->get(route('planung.erinnerungen', ['erledigte' => 1]))->assertOk()
            ->assertSee('260911')->assertSee('260912')->assertSee('260913')->assertSee('260914')->assertSee('gesendet am');
    }

    public function test_it_needs_the_planning_right_and_is_part_of_the_planning_tabs(): void
    {
        $this->timer($this->project('260921'));

        $this->actingAs($this->userWith(['project.view']))->get(route('planung.erinnerungen'))->assertForbidden();
        $this->actingAs($this->userWith(['planning.view']))->get(route('planung.erinnerungen'))->assertOk()->assertSee('Erinnerungen');
    }

    public function test_with_several_organizations_the_list_can_be_filtered_by_organization_and_the_choice_is_remembered(): void
    {
        \App\Models\SystemSetting::set(\App\Models\SystemSetting::MULTI_TENANT_ENABLED, '1');
        $other = Tenant::query()->create(['name' => 'Zweite Orga GmbH', 'short_name' => 'ZWO']);
        $otherGroup = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $other->id, 'name' => 'Zweite Redaktion', 'short_name' => 'ZR', 'sort' => 1, 'active' => true]);
        $otherTemplate = MailTemplate::query()->create(['tenant_id' => $other->id, 'name' => 'Vorlage der zweiten Orga', 'subject' => 'x', 'body' => 'y']);

        $own = $this->project('260941');
        $this->timer($own);
        $foreign = Project::query()->create(['tenant_id' => $other->id, 'source_pn' => '260942', 'title' => 'Fremdprojekt', 'status' => 1, 'schedule_model' => 2]);
        MailTimer::query()->create([
            'tenant_id' => $other->id, 'project_id' => $foreign->id, 'mail_template_id' => $otherTemplate->id, 'reference_type' => 'fixed',
            'fixed_date' => '2026-11-20', 'send_date' => '2026-11-20', 'offset_days' => 0, 'only_if_in_step' => false, 'function_group_ids' => [$otherGroup->id],
        ]);

        $admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'central_admin']);

        // Ohne Wahl: alle Organisationen, mit Organisationsspalte und Auswahlfeld
        $this->actingAs($admin)->get(route('planung.erinnerungen'))->assertOk()
            ->assertSee('Alle Organisationen')->assertSee('260941')->assertSee('260942')->assertSee('Vorlage der zweiten Orga')->assertSee('ZWO');

        // Nur die zweite Organisation
        $this->actingAs($admin)->get(route('planung.erinnerungen', ['organisation' => $other->id]))->assertOk()
            ->assertDontSee('260941')->assertSee('260942')->assertSee('Fremdprojekt');
        // Die Wahl bleibt beim nächsten Besuch erhalten
        $this->actingAs($admin)->get(route('planung.erinnerungen'))->assertOk()->assertDontSee('260941')->assertSee('260942');

        // Zurück auf alle
        $this->actingAs($admin)->get(route('planung.erinnerungen', ['organisation' => 'all']))->assertOk()->assertSee('260941')->assertSee('260942');

        // Wer nur eine Organisation erreicht, sieht kein Auswahlfeld und nur eigene Erinnerungen
        $single = $this->userWith(['planning.view', 'project.view']);
        $this->actingAs($single)->get(route('planung.erinnerungen'))->assertOk()->assertDontSee('Alle Organisationen')->assertDontSee('260942');
    }

    public function test_an_error_and_an_overdue_send_date_are_marked(): void
    {
        $this->timer($this->project('260931'), ['send_date' => '2020-01-01', 'last_error' => 'SMTP nicht erreichbar']);

        $this->actingAs($this->admin())->get(route('planung.erinnerungen'))->assertOk()
            ->assertSee('Fehler')->assertSee('SMTP nicht erreichbar')->assertSee('text-red-700', false)->assertSee('in der Vergangenheit');
    }
}
