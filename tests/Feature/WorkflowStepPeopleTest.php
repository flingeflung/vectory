<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowStepPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_workflow_uses_available_central_function_group(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $customer = Tenant::query()->create(['name' => 'Kunde']);
        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => 'super_admin']);
        $person = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $home->id,
            'first_name' => 'Tina',
            'last_name' => 'Redaktion',
            'active' => true,
        ]);
        $previouslyAssignedPerson = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $customer->id,
            'first_name' => 'Ralf',
            'last_name' => 'Altzuordnung',
            'active' => true,
        ]);
        DB::table('person_tenant')->insert([
            'person_id' => $person->id,
            'tenant_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $homeGroup = FunctionGroup::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $home->id,
            'name' => 'Technische Redaktion',
            'short_name' => 'TR',
        ]);
        DB::table('function_group_tenant')->insert([
            'function_group_id' => $homeGroup->id,
            'tenant_id' => $customer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('function_group_member')->insert([
            'tenant_id' => $home->id,
            'function_group_id' => $homeGroup->id,
            'person_id' => $person->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workflowId = DB::table('workflows')->insertGetId([
            'tenant_id' => $customer->id,
            'short_name' => 'TEST',
            'name' => 'Testworkflow',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workflowStepId = DB::table('workflow_steps')->insertGetId([
            'tenant_id' => $customer->id,
            'workflow_id' => $workflowId,
            'title' => 'Redaktion',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workflow_step_function_group')->insert([
            'tenant_id' => $customer->id,
            'workflow_step_id' => $workflowStepId,
            'function_group_id' => $homeGroup->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $projectId = DB::table('projects')->insertGetId([
            'tenant_id' => $customer->id,
            'source_pn' => '260026',
            'title' => 'Testprojekt',
            'workflow_id' => $workflowId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $projectWorkflowStepId = DB::table('project_workflow_steps')->insertGetId([
            'tenant_id' => $customer->id,
            'project_id' => $projectId,
            'workflow_step_id' => $workflowStepId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_people')->insert([
            'tenant_id' => $customer->id,
            'project_id' => $projectId,
            'function_group_id' => $homeGroup->id,
            'person_id' => $person->id,
            'planned_hours' => 5,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_people')->insert([
            'tenant_id' => $customer->id,
            'project_id' => $projectId,
            'function_group_id' => $homeGroup->id,
            'person_id' => $previouslyAssignedPerson->id,
            'planned_hours' => null,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $customer->id])
            ->get(route('projekte.workflow-steps.personen.form', [$projectId, $projectWorkflowStepId, $homeGroup->id]))
            ->assertOk()
            ->assertSee('Redaktion, Tina')
            ->assertSee('Altzuordnung, Ralf');

        $this->get(route('projekte.projektbeteiligte.show', $projectId))
            ->assertOk()
            ->assertSee('Redaktion, Tina')
            ->assertSee('Altzuordnung, Ralf')
            ->assertSee(__('Im Workflow relevant'))
            ->assertSee(__('Person fehlt'))
            ->assertSee("selectedWorkflowId: '{$workflowId}'", false)
            ->assertSee('project-workflow-selection-changed', false)
            ->assertSee('border-blue-400 bg-blue-50', false)
            ->assertSee('border-amber-400 bg-amber-50', false);

        $this->get(route('projekte.show', $projectId))
            ->assertOk()
            ->assertSee("activeTab = 'planung'", false)
            ->assertSeeInOrder([
                "x-show=\"activeTab === 'planung'\"",
                'project-planned-hours-editor',
                "x-show=\"activeTab === 'zeiten'\"",
                __('Geplante Stunden'),
                __('Gebuchte Stunden'),
                __('Differenz'),
            ], false)
            ->assertSee('project_people_hours['.$homeGroup->id.']['.$person->id.']', false)
            ->assertSee('value="5"', false);
    }
}
