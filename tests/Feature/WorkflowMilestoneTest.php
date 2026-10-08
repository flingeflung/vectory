<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowMilestone;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Meilensteine der Workflow-Vorlage: pflegen, einfrieren, bei Neue Version und Kopieren mitnehmen (Ralf, 2026-10-09). */
class WorkflowMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    /** @var array<int, WorkflowStep> */
    private array $steps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']));
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'PRT', 'name' => 'Print', 'active' => true, 'sort' => 1]);
        foreach ([[1, 'Planung'], [2, 'Anleitung'], [2, 'Druck'], [3, 'Ende']] as $i => [$status, $title]) {
            $this->steps[$i] = WorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $i + 1, 'lifecycle_status' => $status]);
        }
    }

    public function test_milestones_can_be_created_changed_and_deleted(): void
    {
        $this->post(route('admin.workflows.milestones.store', $this->workflow), [
            'name' => 'Markteinführung', 'anchor_type' => 'workflow_end', 'offset_days' => 1, 'is_market_launch' => 1,
        ])->assertRedirect();
        $milestone = WorkflowMilestone::query()->where('workflow_id', $this->workflow->id)->sole();
        $this->assertSame(1, $milestone->offset_days);
        $this->assertTrue($milestone->is_market_launch);

        $this->patch(route('admin.workflows.milestones.update', [$this->workflow, $milestone]), [
            'name' => 'Abnahme', 'anchor_type' => 'step_end', 'anchor_workflow_step_id' => $this->steps[1]->id, 'offset_days' => -2, 'is_market_launch' => 0,
        ])->assertRedirect();
        $milestone->refresh();
        $this->assertSame('Abnahme', $milestone->name);
        $this->assertSame($this->steps[1]->id, $milestone->anchor_workflow_step_id);
        $this->assertSame(-2, $milestone->offset_days);
        $this->assertFalse($milestone->is_market_launch);

        $this->delete(route('admin.workflows.milestones.destroy', [$this->workflow, $milestone]))->assertRedirect();
        $this->assertSame(0, WorkflowMilestone::query()->where('workflow_id', $this->workflow->id)->count());
    }

    public function test_input_is_validated_and_a_published_workflow_is_frozen(): void
    {
        $this->post(route('admin.workflows.milestones.store', $this->workflow), ['name' => '', 'anchor_type' => 'workflow_end'])->assertSessionHasErrors('name');
        // Status-Schritt ist keine Phase (Ablehnung kommt als JSON-Anfrage mit 422, im Browser als Weiterleitung mit Meldung)
        $this->postJson(route('admin.workflows.milestones.store', $this->workflow), ['name' => 'X', 'anchor_type' => 'step_end', 'anchor_workflow_step_id' => $this->steps[0]->id])->assertStatus(422);
        // festes Datum gibt es in der Vorlage nicht
        $this->post(route('admin.workflows.milestones.store', $this->workflow), ['name' => 'X', 'anchor_type' => 'fixed'])->assertSessionHasErrors('anchor_type');

        $this->workflow->update(['published_at' => now()]);
        $this->postJson(route('admin.workflows.milestones.store', $this->workflow), ['name' => 'X', 'anchor_type' => 'workflow_end'])->assertStatus(422);
        $this->assertSame(0, WorkflowMilestone::query()->where('workflow_id', $this->workflow->id)->count());
    }

    public function test_new_version_and_copy_take_the_milestones_along(): void
    {
        WorkflowMilestone::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Abnahme', 'sort' => 1, 'anchor_type' => 'step_end', 'anchor_workflow_step_id' => $this->steps[1]->id, 'offset_days' => 2]);
        WorkflowMilestone::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Ende+1', 'sort' => 2, 'anchor_type' => 'workflow_end', 'offset_days' => 1, 'is_market_launch' => true]);

        $this->workflow->update(['published_at' => now()]);
        $this->post(route('admin.workflows.new-version', $this->workflow))->assertRedirect();
        $this->post(route('admin.workflows.duplicate', $this->workflow))->assertRedirect();

        $copies = Workflow::query()->where('tenant_id', $this->tenant->id)->where('id', '!=', $this->workflow->id)->get();
        $this->assertCount(2, $copies);
        foreach ($copies as $copy) {
            $milestones = WorkflowMilestone::query()->where('workflow_id', $copy->id)->orderBy('sort')->get();
            $this->assertCount(2, $milestones);
            $newStep = WorkflowStep::query()->where('workflow_id', $copy->id)->where('title', 'Anleitung')->sole();
            $this->assertSame($newStep->id, $milestones[0]->anchor_workflow_step_id);
            $this->assertTrue($milestones[1]->is_market_launch);
        }
    }

    public function test_page_lists_the_milestones(): void
    {
        WorkflowMilestone::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'name' => 'Messetermin', 'sort' => 1, 'anchor_type' => 'workflow_end', 'offset_days' => 3]);

        $this->get(route('admin.workflows', ['workflow' => $this->workflow->id]))->assertOk()->assertSee('Messetermin')->assertSee('Workflow-Ende +3 AT');
    }
}
