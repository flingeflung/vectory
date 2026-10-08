<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\ProjectPerson;
use App\Models\ProjectTemplate;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Planung übertragen (Ralf, 2026-10-08): an eine Gruppe oder ein Einzelprojekt senden, von einem Projekt holen. */
class PlanningTransferTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    /** @var array<int, WorkflowStep> */
    private array $steps = [];

    private FunctionGroup $group;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'TR', 'short_name' => 'TR', 'sort' => 1, 'active' => true]);
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'T', 'name' => 'Test', 'active' => true, 'sort' => 1]);
        foreach ([[2, 4], [2, 3]] as $i => [$status, $duration]) {
            $this->steps[$i] = WorkflowStep::query()->create([
                'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => 'S'.($i + 1),
                'sort' => $i + 1, 'lifecycle_status' => $status, 'duration_days' => $duration,
            ]);
        }
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
    }

    private function project(string $pn, ?int $workflowId = null, ?int $role = null, ?int $mainId = null): Project
    {
        return Project::query()->create([
            'tenant_id' => $this->tenant->id, 'source_pn' => $pn, 'title' => 'P'.$pn, 'status' => 0, 'workflow_id' => $workflowId,
            'verbund_rolle' => $role, 'hauptprojekt_id' => $mainId, 'start_date' => '2027-03-01', 'end_date' => '2027-03-31',
        ]);
    }

    private function stepRow(Project $project, WorkflowStep $step, array $values): ProjectWorkflowStep
    {
        return ProjectWorkflowStep::query()->updateOrCreate(
            ['project_id' => $project->id, 'workflow_step_id' => $step->id],
            ['tenant_id' => $this->tenant->id, 'sort' => $step->sort] + $values
        );
    }

    public function test_send_to_a_verbund_group_without_the_main_project(): void
    {
        $main = $this->project('270001', $this->workflow->id, 1);
        $sub1 = $this->project('270002', null, 2, $main->id);
        $sub2 = $this->project('270003', null, 2, $main->id);
        $verbund = ProjectGroup::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Verbund', 'is_verbund' => true]);
        $verbund->projects()->attach([$main->id, $sub1->id, $sub2->id]);

        // Quelle: Dauern, Sperre, Termin, Aufwandsprofil mit eigenen Stunden, eine Person
        $this->stepRow($main, $this->steps[0], ['duration_days' => 9, 'duration_locked' => true, 'due_date' => '2027-03-10']);
        $template = ProjectTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Profil', 'active' => true, 'duration_value' => 1, 'duration_unit' => 'week']);
        $main->update(['project_template_id' => $template->id]);
        $main->functionGroupHours()->sync([$this->group->id => ['tenant_id' => $this->tenant->id, 'planned_hours' => 12]]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Tester', 'active' => true]);
        ProjectPerson::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $main->id, 'function_group_id' => $this->group->id, 'person_id' => $person->id, 'planned_hours' => 5]);

        $response = $this->actingAs($this->admin)->post(route('projekte.planung-uebertragen.run', $main), [
            'direction' => 'send', 'scope' => 'group', 'group_id' => $verbund->id,
            'parts' => ['workflow', 'durations', 'planned_hours', 'people'],
        ]);
        $response->assertOk()->assertSee('270002')->assertSee('270003');

        foreach ([$sub1, $sub2] as $target) {
            $target = $target->fresh();
            $this->assertSame($this->workflow->id, (int) $target->workflow_id);
            $row = ProjectWorkflowStep::query()->where('project_id', $target->id)->where('workflow_step_id', $this->steps[0]->id)->first();
            $this->assertSame(9, (int) $row->duration_days);
            $this->assertTrue((bool) $row->duration_locked);
            $this->assertNull($row->due_date);   // Termine nur auf ausdrücklichen Wunsch
            $this->assertSame($template->id, (int) $target->project_template_id);
            $this->assertSame(12.0, (float) $target->functionGroupHours()->first()->pivot->planned_hours);
            $this->assertSame(1, $target->projectPeople()->count());
        }
    }

    public function test_milestones_only_when_asked_and_keep_existing_leaves_values_alone(): void
    {
        $source = $this->project('270010', $this->workflow->id);
        $target = $this->project('270011', $this->workflow->id);
        $this->stepRow($source, $this->steps[0], ['duration_days' => 6, 'due_date' => '2027-03-15']);
        $this->stepRow($target, $this->steps[0], ['duration_days' => 2]);

        // Vorhandenes behalten: die Dauer 2 bleibt
        $this->actingAs($this->admin)->post(route('projekte.planung-uebertragen.run', $source), [
            'direction' => 'send', 'scope' => 'single', 'other_project_id' => $target->id, 'parts' => ['durations'], 'keep_existing' => 1,
        ])->assertOk();
        $this->assertSame(2, (int) $this->stepRow($target, $this->steps[0], [])->duration_days);

        // Ohne Haken wird überschrieben, Termine kommen nur mit "milestones"
        $this->actingAs($this->admin)->post(route('projekte.planung-uebertragen.run', $source), [
            'direction' => 'send', 'scope' => 'single', 'other_project_id' => $target->id, 'parts' => ['durations'],
        ])->assertOk();
        $row = $this->stepRow($target, $this->steps[0], []);
        $this->assertSame(6, (int) $row->duration_days);
        $this->assertNull($row->due_date);

        $this->actingAs($this->admin)->post(route('projekte.planung-uebertragen.run', $source), [
            'direction' => 'send', 'scope' => 'single', 'other_project_id' => $target->id, 'parts' => ['milestones'],
        ])->assertOk();
        $this->assertSame('2027-03-15', $this->stepRow($target, $this->steps[0], [])->due_date->toDateString());
    }

    public function test_fetch_from_another_project_and_permissions(): void
    {
        $source = $this->project('270020', $this->workflow->id);
        $mine = $this->project('270021');
        $this->stepRow($source, $this->steps[1], ['duration_days' => 8]);

        $this->actingAs($this->admin)->post(route('projekte.planung-uebertragen.run', $mine), [
            'direction' => 'fetch', 'scope' => 'single', 'other_project_id' => $source->id, 'parts' => ['workflow', 'durations'],
        ])->assertOk();
        $this->assertSame($this->workflow->id, (int) $mine->fresh()->workflow_id);
        $this->assertSame(8, (int) $this->stepRow($mine, $this->steps[1], [])->duration_days);

        $plain = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
        $this->actingAs($plain)->get(route('projekte.planung-uebertragen.form', $mine))->assertForbidden();
        $this->actingAs($this->admin)->get(route('projekte.planung-uebertragen.form', $mine))->assertOk()->assertSee('Planung dieses Projekts an andere senden')->assertSee('Es gibt noch keine Gruppe');
    }

    public function test_copy_template_planning_parts_are_applied_when_copying_a_project(): void
    {
        $attribute = \App\Models\Attribute::query()->where('tenant_id', $this->tenant->id)->whereIn('key', ['workflow_id', 'title', 'project_type'])->get();
        $workflowAttribute = $attribute->firstWhere('key', 'workflow_id');
        $titleAttribute = $attribute->firstWhere('key', 'title');
        if ($workflowAttribute === null || $titleAttribute === null) {
            $this->markTestSkipped('System-Attribute fehlen in der Testdatenbank.');
        }

        $source = $this->project('270030', $this->workflow->id);
        $this->stepRow($source, $this->steps[0], ['duration_days' => 7, 'duration_locked' => true, 'due_date' => '2027-03-12']);
        $template = \App\Models\CopyTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mit Planung', 'sort' => 1]);
        $template->fields()->attach([$workflowAttribute->id, $titleAttribute->id]);
        // Feld "Aufwandsprofil" nimmt Profil samt eigenen Planstunden mit
        $profileAttribute = \App\Models\Attribute::query()->where('tenant_id', $this->tenant->id)->where('key', 'project_template')->first();
        $profile = ProjectTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Profil', 'active' => true, 'duration_value' => 1, 'duration_unit' => 'week']);
        $source->update(['project_template_id' => $profile->id]);
        $source->functionGroupHours()->sync([$this->group->id => ['tenant_id' => $this->tenant->id, 'planned_hours' => 6]]);
        if ($profileAttribute) {
            $template->fields()->attach($profileAttribute->id);
        }

        // Planungs-Bereiche in der Vorlage ein-/ausschalten
        $this->actingAs($this->admin)->post(route('admin.projektkopie-vorlagen.update', $template), [
            'name' => 'Mit Planung', 'fields' => $template->fields()->pluck('attributes.id')->all(), 'planning_parts' => ['durations', 'bogus'],
        ])->assertRedirect();
        $this->assertSame(['durations'], $template->fresh()->planningParts());
        $this->actingAs($this->admin)->get(route('admin.projektkopie-vorlagen', ['vorlage' => $template->id]))->assertOk()->assertSee('Dauern und Sperren der Schritte')->assertSee('Termine der Schritte (Meilensteine)');

        $this->actingAs($this->admin)->post(route('projekte.kopieren.store', $source), [
            'template_id' => $template->id, 'count' => 1, 'title' => 'Kopie', 'copy_mode' => 'new',
        ]);
        $copy = Project::query()->where('title', 'Kopie')->first();
        $this->assertNotNull($copy);
        $row = ProjectWorkflowStep::query()->where('project_id', $copy->id)->where('workflow_step_id', $this->steps[0]->id)->first();
        $this->assertSame(7, (int) $row->duration_days);
        $this->assertTrue((bool) $row->duration_locked);
        $this->assertNull($row->due_date);   // Termine nur, wenn die Vorlage sie vorsieht
        if ($profileAttribute) {
            $this->assertSame($profile->id, (int) $copy->project_template_id);
            $this->assertSame(6.0, (float) $copy->functionGroupHours()->first()->pivot->planned_hours);
        }
    }

    public function test_template_form_saves_name_fields_and_planning_parts_together(): void
    {
        $titleAttribute = \App\Models\Attribute::query()->where('tenant_id', $this->tenant->id)->where('key', 'title')->first();
        $workflowAttribute = \App\Models\Attribute::query()->where('tenant_id', $this->tenant->id)->where('key', 'workflow_id')->first();
        $noEffect = \App\Models\Attribute::query()->where('tenant_id', $this->tenant->id)->whereIn('key', \App\Http\Controllers\ProjectCopyController::NO_EFFECT_KEYS)->first();
        if ($titleAttribute === null || $workflowAttribute === null) {
            $this->markTestSkipped('System-Attribute fehlen in der Testdatenbank.');
        }
        $template = \App\Models\CopyTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alt', 'sort' => 1]);
        $template->fields()->attach(array_filter([$titleAttribute->id, $noEffect?->id]));

        $this->actingAs($this->admin)->post(route('admin.projektkopie-vorlagen.update', $template), [
            'name' => 'Neu', 'fields' => [$workflowAttribute->id], 'planning_parts' => ['milestones'],
        ])->assertRedirect();

        $template = $template->fresh();
        $this->assertSame('Neu', $template->name);
        $this->assertSame(['milestones'], $template->planningParts());
        $ids = $template->fields()->pluck('attributes.id')->all();
        $this->assertContains($workflowAttribute->id, $ids);
        $this->assertNotContains($titleAttribute->id, $ids);   // abgehakt = entfernt
        if ($noEffect) {
            $this->assertContains($noEffect->id, $ids);        // Felder ohne Kopier-Wirkung bleiben unberührt
        }
    }

    public function test_empty_source_areas_never_wipe_the_target(): void
    {
        $source = $this->project('270040', $this->workflow->id);   // nichts geplant
        $target = $this->project('270041', $this->workflow->id);
        $template = ProjectTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Profil', 'active' => true, 'duration_value' => 1, 'duration_unit' => 'week']);
        $target->update(['project_template_id' => $template->id]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Bleibt', 'active' => true]);
        ProjectPerson::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $target->id, 'function_group_id' => $this->group->id, 'person_id' => $person->id]);
        $this->stepRow($target, $this->steps[0], ['duration_days' => 5, 'due_date' => '2027-03-20']);

        $this->actingAs($this->admin)->post(route('projekte.planung-uebertragen.run', $source), [
            'direction' => 'send', 'scope' => 'single', 'other_project_id' => $target->id,
            'parts' => ['durations', 'planned_hours', 'people', 'milestones'],
        ])->assertOk()->assertSee('weder ein Aufwandsprofil noch eigene Planstunden')->assertSee('keine Projektbeteiligten');

        $this->assertSame($template->id, (int) $target->fresh()->project_template_id);
        $this->assertSame(1, $target->projectPeople()->count());
        $row = $this->stepRow($target, $this->steps[0], []);
        $this->assertSame(5, (int) $row->duration_days);
        $this->assertSame('2027-03-20', $row->due_date->toDateString());
    }

    public function test_info_reports_which_areas_a_project_has(): void
    {
        $with = $this->project('270050', $this->workflow->id);
        $this->stepRow($with, $this->steps[0], ['duration_days' => 3, 'due_date' => '2027-03-02']);
        $without = $this->project('270051');
        $me = $this->project('270052');

        $this->actingAs($this->admin)->getJson(route('projekte.planung-uebertragen.info', ['project' => $me, 'other' => $with->id]))
            ->assertOk()->assertJson(['workflow' => true, 'durations' => true, 'milestones' => true, 'planned_hours' => false, 'people' => false, 'workflow_id' => $this->workflow->id]);
        $this->actingAs($this->admin)->getJson(route('projekte.planung-uebertragen.info', ['project' => $me, 'other' => $without->id]))
            ->assertOk()->assertJson(['workflow' => false, 'durations' => false, 'workflow_id' => null]);
    }
}
