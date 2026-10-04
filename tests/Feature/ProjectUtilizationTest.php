<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\PersonWeeklyHours;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectPlanningCalculator;
use App\Support\ProjectUtilization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectUtilizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunctionGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'Redaktion', 'short_name' => 'RED', 'sort' => 1, 'active' => true]);
    }

    private function person(string $lastName): Person
    {
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'last_name' => $lastName, 'first_name' => 'Anna', 'active' => true]);
        PersonWeeklyHours::query()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'hours' => 40, 'valid_from' => null, 'valid_to' => null]);

        return $person;
    }

    private function project(string $pn, ?int $role = null, ?int $mainId = null): Project
    {
        return Project::query()->create([
            'tenant_id' => $this->tenant->id, 'source_pn' => $pn, 'title' => 'P'.$pn, 'status' => 0,
            'start_date' => '2027-03-01', 'end_date' => '2027-03-12', 'verbund_rolle' => $role, 'hauptprojekt_id' => $mainId,
        ]);
    }

    private function assign(Project $project, Person $person, float $hours): void
    {
        ProjectPerson::query()->create([
            'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'person_id' => $person->id,
            'function_group_id' => $this->group->id, 'planned_hours' => $hours,
        ]);
    }

    private function data(Project $project, string $mode = 'month', ?int $personId = null): array
    {
        return ProjectUtilization::for($project->fresh(), new ProjectPlanningCalculator, $mode, 2027, 3, $personId);
    }

    public function test_only_this_project_counts_not_the_other_projects_of_the_person(): void
    {
        $anna = $this->person('Muster');
        $mine = $this->project('270001');
        $other = $this->project('270002');
        $this->assign($mine, $anna, 10);
        $this->assign($other, $anna, 100); // darf hier nicht auftauchen

        $entry = $this->data($mine)['people']->first();

        $this->assertEqualsWithDelta(10.0, array_sum(array_column($entry['rows'], 'project')), 0.001);
    }

    public function test_main_project_accumulates_its_sub_projects(): void
    {
        $anna = $this->person('Muster');
        $ben = $this->person('Beispiel');
        $main = $this->project('270001', 1);
        $sub = $this->project('270002', 2, $main->id);
        $this->assign($main, $anna, 10);
        $this->assign($sub, $anna, 6);
        $this->assign($sub, $ben, 4); // nur im Unterprojekt, erscheint trotzdem beim Hauptprojekt

        $data = $this->data($main);

        $this->assertCount(2, $data['people']);
        $this->assertSame(2, $data['members']->count());
        $annaRows = $data['people']->first(fn ($e) => $e['person']->id === $anna->id)['rows'];
        $this->assertEqualsWithDelta(16.0, array_sum(array_column($annaRows, 'project')), 0.001, 'Hauptprojekt und Unterprojekt zusammen.');

        // Das Unterprojekt allein zeigt nur sich selbst.
        $subData = $this->data($sub);
        $this->assertSame(1, $subData['members']->count());
        $this->assertEqualsWithDelta(6.0, array_sum(array_column($subData['people']->first(fn ($e) => $e['person']->id === $anna->id)['rows'], 'project')), 0.001);
    }

    public function test_person_filter_year_mode_and_overbooking(): void
    {
        $anna = $this->person('Muster');
        $ben = $this->person('Beispiel');
        $project = $this->project('270001');
        $this->assign($project, $anna, 100); // 10 Arbeitstage x 8 h = 80 h Kapazität -> 20 h Überbuchung
        $this->assign($project, $ben, 5);

        $filtered = $this->data($project, 'month', $anna->id);
        $this->assertCount(1, $filtered['people']);
        $this->assertCount(2, $filtered['allPeople'], 'Das Dropdown behält alle Personen, auch bei gefiltertem Ergebnis.');

        $chart = $filtered['people']->first()['chart'];
        $this->assertEqualsWithDelta(20.0, array_sum($chart['project_over']), 0.01);
        $this->assertEqualsWithDelta(80.0, array_sum($chart['project_in']), 0.01);

        $year = $this->data($project, 'year');
        $this->assertGreaterThanOrEqual(52, $year['periods']->count());
        $this->assertLessThanOrEqual(53, $year['periods']->count());
    }

    public function test_endpoint_needs_the_planning_permission_and_returns_html_and_people(): void
    {
        $anna = $this->person('Muster');
        $project = $this->project('270001');
        $this->assign($project, $anna, 10);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']));
        $this->getJson(route('projekte.planung.auslastung', ['project' => $project, 'view' => 'month', 'year' => 2027, 'month' => 3]))
            ->assertOk()
            ->assertJsonPath('people.0.name', 'Muster, Anna')
            ->assertJsonFragment(['people' => [['id' => $anna->id, 'name' => 'Muster, Anna']]]);

        $html = $this->getJson(route('projekte.planung.auslastung', ['project' => $project, 'view' => 'month', 'year' => 2027, 'month' => 3]))->json('html');
        $this->assertStringContainsString('canvas', $html);
        $this->assertStringContainsString('Muster', $html);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']));
        $this->getJson(route('projekte.planung.auslastung', ['project' => $project]))->assertForbidden();
    }
}
