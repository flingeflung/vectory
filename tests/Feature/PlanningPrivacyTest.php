<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanningPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributor_without_planning_right_sees_only_personal_project_planning(): void
    {
        [$user, $person, $group, $ownProject] = $this->contributorWithProject();
        $otherPerson = Person::query()->create([
            'tenant_id' => $person->tenant_id,
            'last_name' => 'Kollegin',
            'active' => true,
            'resource_planning' => true,
        ]);
        $otherProject = Project::query()->create([
            'tenant_id' => $person->tenant_id,
            'source_pn' => '260102',
            'title' => 'Fremdes Projekt',
            'status' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        DB::table('project_people')->insert([
            'tenant_id' => $person->tenant_id,
            'project_id' => $otherProject->id,
            'function_group_id' => $group->id,
            'person_id' => $otherPerson->id,
            'planned_hours' => 10,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('planung.projektplanung', ['year' => 2026]))
            ->assertOk()
            ->assertSee($ownProject->title)
            ->assertDontSee($otherProject->title)
            ->assertDontSee('href="'.route('planung.stunden').'"', false);

        $this->assertSame([$person->id], $response->viewData('selectedPersonIds')->all());
        $this->get(route('planung.stunden'))->assertForbidden();
    }

    public function test_contributor_without_planning_right_gets_read_only_project_planning_and_aggregated_times(): void
    {
        [$user, $person, $group, $project] = $this->contributorWithProject();
        DB::table('project_function_group_hours')->insert([
            'tenant_id' => $person->tenant_id,
            'project_id' => $project->id,
            'function_group_id' => $group->id,
            'planned_hours' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('projekte.show', $project))
            ->assertOk()
            ->assertSee(__('Planstunden je Funktionsgruppe'))
            ->assertDontSee(__('Planstunden und Verteilung auf Projektbeteiligte'))
            ->assertDontSee(__('Std. verteilen'))
            ->assertSee(__('Projektstunden'))
            ->assertSee(__('Zeitverlauf'))
            ->assertDontSee(__('Personen & Tage'));

        $this->post(route('projekte.planstunden', $project), [
            'hours' => [$group->id => 99],
        ])->assertForbidden();
        $this->get(route('projekte.zeiten.personen', $project))->assertForbidden();
        $this->get(route('projekte.zeiten.gesamt', $project).'?mode=person')
            ->assertOk()
            ->assertSee(__('Nach Projekten'))
            ->assertDontSee(__('Nach Personen'));
    }

    private function contributorWithProject(): array
    {
        $tenant = Tenant::query()->firstOrFail();
        $permissions = Permission::query()->whereIn('key', ['project.view', 'project.edit'])->pluck('id');
        $rights = PermissionTemplate::query()->create(['tenant_id' => $tenant->id, 'name' => 'Projektmitarbeit']);
        $rights->permissions()->attach($permissions);
        $person = Person::query()->create([
            'tenant_id' => $tenant->id,
            'last_name' => 'Mitarbeiter',
            'active' => true,
            'resource_planning' => true,
            'permission_template_id' => $rights->id,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'person_id' => $person->id,
            'role' => 'user',
        ]);
        $group = FunctionGroup::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Technische Redaktion',
            'short_name' => 'TR',
            'active' => true,
        ]);
        DB::table('function_group_member')->insert([
            'tenant_id' => $tenant->id,
            'function_group_id' => $group->id,
            'person_id' => $person->id,
        ]);
        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'source_pn' => '260101',
            'title' => 'Eigenes Projekt',
            'status' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        DB::table('project_people')->insert([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'function_group_id' => $group->id,
            'person_id' => $person->id,
            'planned_hours' => 12,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $person, $group, $project];
    }
}
