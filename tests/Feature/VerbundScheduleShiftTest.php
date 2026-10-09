<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Termine eines Verbunds gemeinsam verschieben, am Hauptprojekt (Ralf, 2026-10-09). */
class VerbundScheduleShiftTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    private Project $main;

    private Project $sub1;

    private Project $sub2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'T', 'name' => 'Test', 'active' => true, 'sort' => 1]);
        foreach ([['In Planung', 1, 1, 1], ['A', 2, 2, 2], ['B', 2, 3, 3], ['Ende', 3, 4, 1]] as [$title, $lifecycle, $sort, $days]) {
            WorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => $title, 'sort' => $sort, 'lifecycle_status' => $lifecycle, 'duration_days' => $days]);
        }

        // Mo 1.3.2027: A = Mo-Di, B = Mi-Fr, Projektende Fr 5.3.
        $this->main = Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '270001', 'title' => 'Serie', 'status' => 0, 'start_date' => '2027-03-01', 'end_date' => '2027-03-31', 'verbund_rolle' => 1]);
        $this->sub1 = $this->sub('270002', ['workflow_id' => $this->workflow->id, 'start_date' => '2027-03-01', 'schedule_model' => 2]);
        $this->sub2 = $this->sub('270003', ['start_date' => null]);
    }

    private function sub(string $pn, array $attributes): Project
    {
        return Project::query()->create([...['tenant_id' => $this->tenant->id, 'source_pn' => $pn, 'title' => 'Unter '.$pn, 'status' => 0, 'verbund_rolle' => 2, 'hauptprojekt_id' => $this->main->id], ...$attributes]);
    }

    private function admin(): User
    {
        return User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);
    }

    private function url(): string
    {
        return route('projekte.termine.verbund-verschieben', $this->main);
    }

    public function test_the_preview_shows_every_project_with_new_start_and_end_and_skips_those_without_a_start_date(): void
    {
        $json = $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'days', 'amount' => 5, 'unit' => 'at', 'direction' => 'later', 'preview' => 1])->assertOk()->json();

        $this->assertSame(5, $json['delta']);
        $rows = collect($json['projects'])->keyBy('pn');
        // Hauptprojekt ohne Workflow: Anfang und Ende wandern um fünf Arbeitstage
        $this->assertSame('2027-03-08', $rows['270001']['startNew']);
        $this->assertSame('2027-04-07', $rows['270001']['endNew']);
        // Unterprojekt mit Workflow: neuer Start, das Ende folgt aus den Dauern (Fr 12.3.)
        $this->assertSame('2027-03-08', $rows['270002']['startNew']);
        $this->assertSame('2027-03-12', $rows['270002']['endNew']);
        $this->assertNull($rows['270002']['reason']);
        // Ohne Startdatum wird übersprungen
        $this->assertNotNull($rows['270003']['reason']);
        $this->assertNull($rows['270003']['startNew']);
        $this->assertSame(2, $json['movable']);
        // Nur Vorschau: nichts geschrieben
        $this->assertSame('2027-03-01', $this->sub1->fresh()->start_date->format('Y-m-d'));
    }

    public function test_saving_moves_all_projects_recalculates_and_logs_each_project(): void
    {
        $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'days', 'amount' => 1, 'unit' => 'weeks', 'direction' => 'later'])->assertOk()->assertJsonPath('applied', 2);

        $this->assertSame('2027-03-08', $this->main->fresh()->start_date->format('Y-m-d'));
        $this->assertSame('2027-04-07', $this->main->fresh()->end_date->format('Y-m-d'));
        $sub = $this->sub1->fresh();
        $this->assertSame('2027-03-08', $sub->start_date->format('Y-m-d'));
        $this->assertSame('2027-03-12', $sub->end_date->format('Y-m-d'));
        $this->assertNull($this->sub2->fresh()->start_date);

        $log = Activity::query()->where('project_id', $this->sub1->id)->where('type', ActivityType::ScheduleShifted->value)->get();
        $this->assertCount(1, $log);
        $this->assertStringContainsString('+5', $log[0]->message);
        $this->assertStringContainsString('01.03.2027', $log[0]->message);
        $this->assertSame(0, Activity::query()->where('project_id', $this->sub2->id)->where('type', ActivityType::ScheduleShifted->value)->count());
    }

    public function test_earlier_moves_backwards_over_the_weekend(): void
    {
        $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'days', 'amount' => 1, 'unit' => 'at', 'direction' => 'earlier'])->assertOk();

        // Montag minus ein Arbeitstag = Freitag davor
        $this->assertSame('2027-02-26', $this->sub1->fresh()->start_date->format('Y-m-d'));
    }

    public function test_putting_one_project_on_a_new_start_date_derives_the_distance_in_workdays(): void
    {
        $json = $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'project', 'project_id' => $this->sub1->id, 'date' => '2027-03-15', 'preview' => 1])->assertOk()->json();

        // Mo 1.3. -> Mo 15.3. = 10 Arbeitstage; das Hauptprojekt zieht mit
        $this->assertSame(10, $json['delta']);
        $this->assertSame('2027-03-15', collect($json['projects'])->firstWhere('pn', '270001')['startNew']);

        $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'project', 'project_id' => $this->sub1->id, 'date' => '2027-02-22'])->assertOk();
        $this->assertSame('2027-02-22', $this->sub1->fresh()->start_date->format('Y-m-d'));
        $this->assertSame('2027-02-22', $this->main->fresh()->start_date->format('Y-m-d'));
    }

    public function test_milestones_with_a_fixed_date_stay_and_are_named_in_the_preview(): void
    {
        ProjectMilestone::query()->withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->sub1->id, 'name' => 'Messe', 'anchor_type' => 'fixed', 'fixed_date' => '2027-03-20', 'date' => '2027-03-20', 'offset_days' => 0, 'sort' => 1]);

        $json = $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'days', 'amount' => 5, 'unit' => 'at', 'direction' => 'later', 'preview' => 1])->assertOk()->json();
        $this->assertSame([['pn' => '270002', 'name' => 'Messe', 'date' => '20.03.2027']], $json['fixedMilestones']);

        $this->actingAs($this->admin())->postJson($this->url(), ['mode' => 'days', 'amount' => 5, 'unit' => 'at', 'direction' => 'later'])->assertOk();
        $this->assertSame('2027-03-20', ProjectMilestone::query()->withoutGlobalScopes()->where('name', 'Messe')->first()->date->format('Y-m-d'));
    }

    public function test_it_needs_the_schedule_right_and_works_only_on_a_main_project_and_with_a_real_shift(): void
    {
        $set = PermissionTemplate::query()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nur ansehen']);
        $set->permissions()->attach(Permission::query()->whereIn('key', ['project.view'])->pluck('id'));
        $person = Person::query()->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Leser', 'active' => true, 'permission_template_id' => $set->id]);
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'role' => 'user']);

        $this->actingAs($viewer)->postJson($this->url(), ['mode' => 'days', 'amount' => 5, 'unit' => 'at', 'direction' => 'later'])->assertForbidden();
        $this->assertSame('2027-03-01', $this->sub1->fresh()->start_date->format('Y-m-d'));

        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('projekte.termine.verbund-verschieben', $this->sub1), ['mode' => 'days', 'amount' => 5, 'unit' => 'at', 'direction' => 'later'])->assertStatus(422);
        $this->actingAs($admin)->postJson($this->url(), ['mode' => 'days', 'amount' => 0, 'unit' => 'at', 'direction' => 'later'])->assertStatus(422);
        $this->actingAs($admin)->postJson($this->url(), ['mode' => 'project', 'project_id' => 999999, 'date' => '2027-03-15'])->assertStatus(422);
    }

    public function test_the_button_appears_only_on_the_main_project(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('projekte.show', $this->main))->assertOk()->assertSee('Termine im Verbund verschieben');
        $this->actingAs($admin)->get(route('projekte.show', $this->sub1))->assertOk()->assertDontSee('Termine im Verbund verschieben');
    }
}
