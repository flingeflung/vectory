<?php

namespace Tests\Feature;

use App\Models\CriticalProjectFinding;
use App\Models\CriticalProjectFindingState;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectWorkflowStep;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\CriticalProjects\CriticalProjectEvaluator;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_started_planned_project_and_explains_rule(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);
        $critical = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269901',
            'title' => 'Verspäteter Projektstart',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);
        $uncritical = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269902',
            'title' => 'Künftiges Projekt',
            'status' => 0,
            'start_date' => today()->addDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee($critical->source_pn)
            ->assertSee('Projektstart erreicht, Status noch geplant')
            ->assertSee('Mögliche Lösung')
            ->assertDontSee('Alle zur Kenntnis nehmen')
            ->assertDontSee($uncritical->source_pn);

        $this->get(route('projekte.show', $critical))
            ->assertOk()
            ->assertSee('Fehlercheck: 1 Befund')
            ->assertSee('Projektstatus')
            ->assertSee('Mögliche Lösung');
    }

    public function test_reason_filter_keeps_only_matching_findings(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269903',
            'title' => 'Filterprojekt',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index', ['reason' => 'staffing.missing']))
            ->assertOk()
            ->assertSee('keine kritischen Projekte gefunden')
            ->assertDontSee('269903');
    }

    public function test_severity_filter_removes_other_findings_from_project_row(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'short_name' => 'FILTER',
            'name' => 'Filterworkflow',
            'active' => true,
        ]);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269913',
            'title' => 'Projekt mit mehreren Dringlichkeiten',
            'workflow_id' => $workflow->id,
            'status' => 0,
        ]);

        foreach ([
            ['title' => 'Projektstart', 'is_start' => true, 'is_end' => false],
            ['title' => 'Projektende', 'is_start' => false, 'is_end' => true],
        ] as $sort => $definition) {
            $step = WorkflowStep::query()->create([
                'tenant_id' => $tenant->id,
                'workflow_id' => $workflow->id,
                'title' => $definition['title'],
                'sort' => $sort + 1,
                'is_active' => true,
                'has_due_date' => true,
                'is_start' => $definition['is_start'],
                'is_end' => $definition['is_end'],
            ]);
            ProjectWorkflowStep::query()->create([
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'workflow_step_id' => $step->id,
                'sort' => $sort + 1,
                'is_current' => $sort === 0,
            ]);
        }

        $response = $this->actingAs($user)->get(route('critical-projects.index', ['severity' => 'critical']))
            ->assertOk()
            ->assertSee($project->source_pn);

        $row = $response->viewData('rows')->first(fn ($row) => $row['project']->is($project));
        $this->assertNotNull($row);
        $this->assertSame(['critical'], $row['findings']->pluck('severity')->unique()->values()->all());
    }

    public function test_project_organization_filter_is_independent_from_global_organization_switch(): void
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $home = Tenant::query()->firstOrFail();
        $other = Tenant::query()->create(['name' => 'Andere Organisation', 'short_name' => 'AND']);
        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => 'super_admin']);
        $homeProject = Project::query()->create([
            'tenant_id' => $home->id,
            'source_pn' => '269911',
            'title' => 'Kritisch bei Heimat',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);
        $otherWorkflow = Workflow::query()->create([
            'tenant_id' => $other->id,
            'short_name' => 'FREMD',
            'name' => 'Workflow der anderen Organisation',
            'active' => true,
        ]);
        $otherProject = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => $other->id,
            'source_pn' => '269912',
            'title' => 'Kritisch bei anderer Organisation',
            'workflow_id' => $otherWorkflow->id,
            'status' => 1,
        ]);
        $otherStep = WorkflowStep::query()->create([
            'tenant_id' => $other->id,
            'workflow_id' => $otherWorkflow->id,
            'title' => 'Überfälliger Fremdtermin',
            'sort' => 1,
            'is_active' => true,
            'has_due_date' => true,
        ]);
        ProjectWorkflowStep::query()->create([
            'tenant_id' => $other->id,
            'project_id' => $otherProject->id,
            'workflow_step_id' => $otherStep->id,
            'sort' => 1,
            'is_current' => true,
            'due_date' => today()->subDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee($homeProject->source_pn)
            ->assertSee($otherProject->source_pn);

        $this->get(route('critical-projects.index', [
            'organization_filter_submitted' => 1,
            'organizations' => [$home->id],
        ]))->assertOk()
            ->assertSee($homeProject->source_pn)
            ->assertDontSee($otherProject->source_pn);

        $this->post(route('mandant.wechseln'), ['tenant_id' => $other->id])->assertRedirect();
        $this->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee($homeProject->source_pn)
            ->assertDontSee($otherProject->source_pn)
            ->assertSee(route('critical-projects.projects.open', $homeProject), false);

        $openResponse = $this->get(route('critical-projects.projects.open', $homeProject));
        $openResponse->assertRedirect(route('critical-projects.index'))
            ->assertSessionHas('open_project_from_critical_projects', $homeProject->id);
        $this->assertSame($home->id, CurrentTenant::id());
        $this->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee("id: {$homeProject->id}", false);

        $this->get(route('critical-projects.index', ['organization_filter_submitted' => 1]))
            ->assertOk()
            ->assertDontSee($homeProject->source_pn)
            ->assertDontSee($otherProject->source_pn);
    }

    public function test_user_can_acknowledge_hide_and_restore_a_finding(): void
    {
        SystemSetting::set(SystemSetting::CRITICAL_PROJECT_ACKNOWLEDGEMENT_ENABLED, '1');
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269907',
            'title' => 'Persönlicher Befundstatus',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index'))->assertOk();
        $finding = CriticalProjectFinding::query()->where('project_id', $project->id)->firstOrFail();

        $this->patch(route('critical-projects.findings.update', $finding), ['action' => 'acknowledge'])
            ->assertRedirect();
        $this->assertNotNull(CriticalProjectFindingState::query()->firstOrFail()->acknowledged_at);

        $this->patch(route('critical-projects.findings.update', $finding), [
            'action' => 'hide',
            'hidden_until' => today()->addWeek()->format('Y-m-d'),
        ])->assertRedirect()
            ->assertSessionHas('open_critical_project_modal', $project->id);

        $response = $this->get(route('critical-projects.index'))->assertOk();
        $this->assertFalse($response->viewData('rows')->contains(fn ($row) => $row['project']->is($project)));
        $this->get(route('critical-projects.index', ['show_hidden' => 1]))
            ->assertOk()
            ->assertSee($project->source_pn)
            ->assertSee('1 ausgeblendeter Befund')
            ->assertSee('Ausgeblendet bis');

        $this->patch(route('critical-projects.findings.update', $finding), ['action' => 'restore'])
            ->assertRedirect()
            ->assertSessionHas('open_critical_project_modal', $project->id);
        $this->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee($project->source_pn)
            ->assertSee('Zur Kenntnis genommen');
    }

    public function test_hidden_finding_is_removed_from_row_when_project_has_another_visible_finding(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'short_name' => 'HIDDEN',
            'name' => 'Workflow für Ausblendtest',
            'active' => true,
        ]);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269914',
            'title' => 'Projekt mit sichtbarem und ausgeblendetem Befund',
            'status' => 0,
            'workflow_id' => $workflow->id,
            'start_date' => today()->subDay(),
        ]);
        foreach (['Projektstart', 'Projektende'] as $sort => $title) {
            $step = WorkflowStep::query()->create([
                'tenant_id' => $tenant->id,
                'workflow_id' => $workflow->id,
                'title' => $title,
                'sort' => $sort + 1,
                'is_active' => true,
                'has_due_date' => true,
                'is_start' => $sort === 0,
                'is_end' => $sort === 1,
            ]);
            ProjectWorkflowStep::query()->create([
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'workflow_step_id' => $step->id,
                'sort' => $sort + 1,
                'is_current' => $sort === 0,
            ]);
        }

        $this->actingAs($user)->get(route('critical-projects.index'))->assertOk();
        $findings = CriticalProjectFinding::query()->where('project_id', $project->id)->get();
        $this->assertGreaterThanOrEqual(2, $findings->count());
        CriticalProjectFindingState::query()->create([
            'critical_project_finding_id' => $findings->first()->id,
            'user_id' => $user->id,
            'hidden_until' => today()->addDay(),
        ]);

        $response = $this->get(route('critical-projects.index'))->assertOk();
        $row = $response->viewData('rows')->first(fn ($row) => $row['project']->is($project));

        $this->assertNotNull($row);
        $this->assertCount($findings->count() - 1, $row['findings']);
        $this->assertFalse($row['findings']->contains(fn ($finding) => $finding['is_hidden']));
    }

    public function test_resolved_and_recurring_cause_creates_a_new_finding_occurrence(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269908',
            'title' => 'Wiederkehrender Befund',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index'))->assertOk();
        $first = CriticalProjectFinding::query()->where('project_id', $project->id)->firstOrFail();
        CriticalProjectFindingState::query()->create([
            'critical_project_finding_id' => $first->id,
            'user_id' => $user->id,
            'acknowledged_at' => now(),
        ]);

        $project->update(['start_date' => today()->addDay()]);
        $this->get(route('critical-projects.index'))->assertOk();
        $this->assertDatabaseMissing('critical_project_findings', ['id' => $first->id]);
        $this->assertDatabaseCount('critical_project_finding_states', 0);

        $project->update(['start_date' => today()->subDay()]);
        $this->get(route('critical-projects.index'))->assertOk();
        $second = CriticalProjectFinding::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertDatabaseCount('critical_project_finding_states', 0);
    }

    public function test_user_can_acknowledge_and_hide_all_findings_of_one_project(): void
    {
        SystemSetting::set(SystemSetting::CRITICAL_PROJECT_ACKNOWLEDGEMENT_ENABLED, '1');
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269910',
            'title' => 'Sammelaktion',
            'status' => 0,
        ]);
        $findings = collect(['rule.one:project', 'rule.two:project'])->map(fn ($key) => CriticalProjectFinding::query()->create([
            'project_id' => $project->id,
            'finding_key' => $key,
            'rule_code' => str($key)->before(':')->toString(),
        ]));

        $this->actingAs($user)->patch(route('critical-projects.findings.bulk-update'), [
            'action' => 'acknowledge',
            'finding_ids' => $findings->pluck('id')->all(),
        ])->assertRedirect();
        $this->assertSame(2, CriticalProjectFindingState::query()->whereNotNull('acknowledged_at')->count());

        $hiddenUntil = today()->addWeek()->format('Y-m-d');
        $this->patch(route('critical-projects.findings.bulk-update'), [
            'action' => 'hide',
            'hidden_until' => $hiddenUntil,
            'finding_ids' => $findings->pluck('id')->all(),
        ])->assertRedirect();
        $this->assertSame(2, CriticalProjectFindingState::query()->whereDate('hidden_until', $hiddenUntil)->count());
    }

    public function test_finishing_project_deletes_findings_and_personal_states(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269909',
            'title' => 'Abgeschlossenes Befundprojekt',
            'status' => 0,
            'start_date' => today()->subDay(),
        ]);

        $this->actingAs($user)->get(route('critical-projects.index'))->assertOk();
        $finding = CriticalProjectFinding::query()->where('project_id', $project->id)->firstOrFail();
        CriticalProjectFindingState::query()->create([
            'critical_project_finding_id' => $finding->id,
            'user_id' => $user->id,
            'acknowledged_at' => now(),
        ]);

        $project->update(['status' => 2]);

        $this->assertDatabaseCount('critical_project_findings', 0);
        $this->assertDatabaseCount('critical_project_finding_states', 0);
    }

    public function test_planned_hours_resolve_template_from_another_organization(): void
    {
        $activeTenant = Tenant::query()->firstOrFail();
        $otherTenant = Tenant::query()->create(['name' => 'Andere Organisation', 'short_name' => 'AND']);
        $user = User::factory()->create(['tenant_id' => $activeTenant->id, 'role' => 'super_admin']);
        $this->actingAs($user);

        $template = ProjectTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Fremde Aufwandsschablone',
            'format' => 1,
            'reusable_content_share' => 1,
            'languages_count' => 1,
            'product_maturity' => 1,
            'product_change_delays' => 1,
            'contact_availability' => 1,
            'localizer_availability' => 1,
            'software_share' => 1,
            'product_complexity' => 1,
            'print_variants_count' => 1,
            'images_count' => 1,
            'duration_value' => 1,
            'duration_unit' => 'Tage',
            'active' => true,
        ]);
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'source_pn' => '269904',
            'title' => 'Projekt einer anderen Organisation',
            'status' => 1,
            'project_template_id' => $template->id,
        ]);

        $this->assertTrue($project->projectTemplate()->exists());
        $this->assertSame(0.0, $project->effectivePlannedHours());
    }

    public function test_finished_project_keeps_button_but_is_not_checked(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269905',
            'title' => 'Beendetes Projekt',
            'status' => 2,
            'start_date' => today()->subYear(),
        ]);

        $this->actingAs($user)->get(route('projekte.show', $project))
            ->assertOk()
            ->assertSee('Fehlercheck: keine Befunde')
            ->assertSee('Beendete und verworfene Projekte werden nicht mehr geprüft.');
    }

    public function test_missing_workflow_dates_are_ranked_by_context(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $workflow = Workflow::query()->create([
            'tenant_id' => $tenant->id,
            'short_name' => 'TERM',
            'name' => 'Terminprüfung',
            'active' => true,
        ]);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '269906',
            'title' => 'Projekt mit fehlenden Terminen',
            'workflow_id' => $workflow->id,
            'status' => 0,
        ]);
        $definitions = [
            ['title' => 'Projektstart', 'is_current' => true, 'is_start' => true, 'is_end' => false],
            ['title' => 'Zwischentermin', 'is_current' => false, 'is_start' => false, 'is_end' => false],
            ['title' => 'Projektende', 'is_current' => false, 'is_start' => false, 'is_end' => true],
        ];
        foreach ($definitions as $sort => $definition) {
            $step = WorkflowStep::query()->create([
                'tenant_id' => $tenant->id,
                'workflow_id' => $workflow->id,
                'title' => $definition['title'],
                'sort' => $sort + 1,
                'is_active' => true,
                'has_due_date' => true,
                'is_start' => $definition['is_start'],
                'is_end' => $definition['is_end'],
            ]);
            ProjectWorkflowStep::query()->create([
                'tenant_id' => $tenant->id,
                'project_id' => $project->id,
                'workflow_step_id' => $step->id,
                'sort' => $sort + 1,
                'is_current' => $definition['is_current'],
            ]);
        }

        $project->load(['projectWorkflowSteps.workflowStep.functionGroups', 'projectPeople', 'functionGroupHours', 'projectTemplate.functionGroups']);
        $findings = app(CriticalProjectEvaluator::class)->evaluate($project, 0);

        $this->assertSame('blocked', $findings->first(fn ($finding) => str_contains($finding['detail'], 'Projektstart'))['severity']);
        $this->assertSame('watch', $findings->first(fn ($finding) => str_contains($finding['detail'], 'Zwischentermin'))['severity']);
        $this->assertSame('critical', $findings->first(fn ($finding) => str_contains($finding['detail'], 'Projektende'))['severity']);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $this->actingAs($user)->get(route('critical-projects.index'))
            ->assertOk()
            ->assertSee('1 Punkt zum');
    }
}
