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
        $this->actingAs($this->admin)->get(route('projekte.planung-uebertragen.form', $mine))->assertOk()->assertSee('Planung dieses Projekts an andere senden');
    }
}
