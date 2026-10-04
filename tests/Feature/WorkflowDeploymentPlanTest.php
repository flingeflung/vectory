<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\Workflow;
use App\Models\WorkflowGroupWindow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowDeploymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Workflow $workflow;

    /** @var array<int, WorkflowStep> */
    private array $steps = [];

    private FunctionGroup $translation;

    private FunctionGroup $editing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
        $this->actingAs($this->admin);

        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'PRT', 'name' => 'Print', 'active' => true, 'sort' => 1]);
        $this->translation = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'Übersetzung', 'short_name' => 'UES', 'sort' => 1, 'active' => true]);
        $this->editing = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'Redaktion', 'short_name' => 'RED', 'sort' => 2, 'active' => true]);

        // Planung (1), vier Arbeitsschritte (2), Beendet (3)
        foreach ([[1, 'In Planung'], [2, 'Datenpflege'], [2, 'Anleitung'], [2, 'Übersetzung'], [2, 'Publikation'], [3, 'Beendet']] as $i => [$status, $title]) {
            $this->steps[$i] = WorkflowStep::query()->create([
                'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $i + 1, 'lifecycle_status' => $status,
            ]);
        }
        $this->steps[1]->functionGroups()->sync([$this->editing->id => ['tenant_id' => $this->tenant->id]]);
        $this->steps[3]->functionGroups()->sync([$this->translation->id => ['tenant_id' => $this->tenant->id]]);
        $this->steps[4]->functionGroups()->sync([$this->editing->id => ['tenant_id' => $this->tenant->id]]);
    }

    private function save(array $windows, ?Workflow $workflow = null)
    {
        return $this->post(route('admin.workflows.deployment-plan', $workflow ?? $this->workflow), ['windows' => $windows]);
    }

    public function test_page_shows_one_row_per_responsible_group_and_only_work_steps(): void
    {
        $this->get(route('admin.workflows', ['workflow' => $this->workflow->id]))
            ->assertOk()->assertSee('Einsatzplan')->assertSee('Übersetzung')->assertSee('Redaktion');
    }

    public function test_window_is_saved_and_full_width_removes_it(): void
    {
        $this->save([$this->translation->id => ['from' => $this->steps[2]->id, 'to' => $this->steps[3]->id]])->assertRedirect();

        $window = WorkflowGroupWindow::query()->where('function_group_id', $this->translation->id)->firstOrFail();
        $this->assertSame($this->steps[2]->id, $window->from_step_id);
        $this->assertSame($this->steps[3]->id, $window->to_step_id);

        // Ganze Breite (erster bis letzter Arbeitsschritt) ist der Standard und wird nicht gespeichert.
        $this->save([$this->translation->id => ['from' => $this->steps[1]->id, 'to' => $this->steps[4]->id]]);
        $this->assertSame(0, WorkflowGroupWindow::query()->count());
    }

    public function test_invalid_rows_are_ignored(): void
    {
        $this->save([
            // Reihenfolge verkehrt, Randschritt (Beendet) und fremde Gruppe werden nicht gespeichert
            $this->translation->id => ['from' => $this->steps[3]->id, 'to' => $this->steps[2]->id],
            $this->editing->id => ['from' => $this->steps[1]->id, 'to' => $this->steps[5]->id],
            999999 => ['from' => $this->steps[1]->id, 'to' => $this->steps[2]->id],
        ])->assertRedirect();

        $this->assertSame(0, WorkflowGroupWindow::query()->count());
    }

    public function test_published_workflow_is_frozen(): void
    {
        $this->workflow->update(['published_at' => now()]);

        $this->save([$this->translation->id => ['from' => $this->steps[2]->id, 'to' => $this->steps[3]->id]])->assertRedirect(); // abgelehnt: freundliche Weiterleitung statt Fehlerseite
        $this->assertSame(0, WorkflowGroupWindow::query()->count());
    }

    public function test_plan_travels_with_a_new_version_and_a_copy(): void
    {
        $this->save([$this->translation->id => ['from' => $this->steps[2]->id, 'to' => $this->steps[3]->id]]);

        $this->post(route('admin.workflows.duplicate', $this->workflow))->assertRedirect();
        $copy = Workflow::query()->where('name', 'like', '%(Kopie)%')->firstOrFail();
        $window = WorkflowGroupWindow::query()->where('workflow_id', $copy->id)->firstOrFail();
        $copySteps = WorkflowStep::query()->where('workflow_id', $copy->id)->orderBy('sort')->pluck('id')->all();
        $this->assertSame($copySteps[2], $window->from_step_id, 'Verweis zeigt auf die Schritte der Kopie, nicht des Originals.');
        $this->assertSame($copySteps[3], $window->to_step_id);

        $this->workflow->update(['published_at' => now()]);
        $this->post(route('admin.workflows.new-version', $this->workflow))->assertRedirect();
        $this->assertSame(3, WorkflowGroupWindow::query()->count());
    }

    public function test_collapse_state_is_remembered_per_user(): void
    {
        $this->postJson(route('admin.workflows.view-state'), ['section' => 'einsatzplan', 'open' => false])->assertNoContent();

        $this->assertFalse(UserPreference::configFor($this->admin->id, UserPreference::WORKFLOW_VIEW)['einsatzplan']);
        $this->get(route('admin.workflows', ['workflow' => $this->workflow->id]))->assertOk()->assertSee('open: false', false);

        $this->postJson(route('admin.workflows.view-state'), ['section' => 'unbekannt', 'open' => true])->assertStatus(422);
    }
}
