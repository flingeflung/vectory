<?php

namespace Tests\Feature;

use App\Enums\GraphicOrderStatus;
use App\Enums\TaskSource;
use App\Models\FunctionGroup;
use App\Models\GraphicOrder;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\ProjectWorkflowStep;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Meldungen der Startseite (Ralf, 2026-10-10). */
class DashboardMessagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Tenant, 1: User, 2: Person, 3: FunctionGroup} */
    private function setUpUser(): array
    {
        $tenant = Tenant::query()->firstOrFail();
        $person = Person::query()->create(['tenant_id' => $tenant->id, 'last_name' => 'Meldung', 'short_name' => 'ME', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'person_id' => $person->id, 'role' => 'super_admin']);
        $group = FunctionGroup::query()->create(['tenant_id' => $tenant->id, 'name' => 'Redaktion', 'short_name' => 'TR', 'sort' => 1, 'active' => true]);

        return [$tenant, $user, $person, $group];
    }

    public function test_without_anything_to_report_the_tile_says_so(): void
    {
        [, $user] = $this->setUpUser();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('derzeit keine Meldungen');
    }

    public function test_only_own_critical_projects_are_counted(): void
    {
        [$tenant, $user, $person, $group] = $this->setUpUser();
        $own = Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '283001', 'title' => 'Eigenes', 'status' => 0, 'start_date' => today()->subDay()]);
        Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '283002', 'title' => 'Fremdes', 'status' => 0, 'start_date' => today()->subDay()]);
        ProjectPerson::query()->create(['tenant_id' => $tenant->id, 'project_id' => $own->id, 'function_group_id' => $group->id, 'person_id' => $person->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('kritisches Projekt')
            ->assertDontSee('derzeit keine Meldungen')
            ->assertSee(route('critical-projects.index'), false);
    }

    public function test_pending_approval_of_the_own_task_is_counted(): void
    {
        [$tenant, $user, $person, $group] = $this->setUpUser();
        $workflow = Workflow::query()->create(['tenant_id' => $tenant->id, 'short_name' => 'FW', 'name' => 'Freigabe-Workflow', 'active' => true]);
        $step = WorkflowStep::query()->create(['tenant_id' => $tenant->id, 'workflow_id' => $workflow->id, 'title' => 'Freigabe', 'sort' => 1, 'is_active' => true, 'js_function' => 'wfs_freigabe']);
        $project = Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '283003', 'title' => 'Mit Freigabe', 'status' => 1, 'workflow_id' => $workflow->id]);
        $projectStep = ProjectWorkflowStep::query()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $step->id, 'sort' => 1, 'is_current' => true]);
        Task::query()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id, 'person_id' => $person->id, 'function_group_id' => $group->id, 'project_workflow_step_id' => $projectStep->id, 'source' => TaskSource::WorkflowStep]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Freigabe wartet auf Sie');

        $projectStep->forceFill(['milestone_done_at' => now()])->save();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Freigabe wartet auf Sie');
    }

    public function test_open_illustration_order_without_illustrator_is_counted_for_its_initiator(): void
    {
        [$tenant, $user, $person] = $this->setUpUser();
        $other = Person::query()->create(['tenant_id' => $tenant->id, 'last_name' => 'Anderer', 'short_name' => 'AN', 'active' => true]);
        $project = Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '283004', 'title' => 'Mit Bildern', 'status' => 1]);
        $order = fn (array $attributes) => GraphicOrder::query()->create($attributes + [
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'graphic_order_status_id' => GraphicOrderStatus::NeuerAuftrag,
            'description' => 'Bild', 'image_count' => 1,
        ]);
        $order(['initiated_by_person_id' => $person->id]);
        $order(['initiated_by_person_id' => $other->id]);
        $order(['initiated_by_person_id' => $person->id, 'graphic_order_status_id' => GraphicOrderStatus::FertigUndAbgelegt]);
        $order(['initiated_by_person_id' => $person->id, 'illustrator_person_id' => $other->id]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Illustrationsauftrag ohne Illustrator')
            ->assertDontSee('Illustrationsaufträge ohne Illustrator');
    }

    public function test_user_without_person_gets_no_messages(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        Project::query()->create(['tenant_id' => $tenant->id, 'source_pn' => '283005', 'title' => 'Egal', 'status' => 0, 'start_date' => today()->subDay()]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('derzeit keine Meldungen');
    }
}
