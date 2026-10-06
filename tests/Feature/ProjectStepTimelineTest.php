<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowGroupWindow;
use App\Models\WorkflowStep;
use App\Services\ProjectPlanningCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStepTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunctionGroup $group;

    private Workflow $workflow;

    /** @var array<int, WorkflowStep> */
    private array $steps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'Übersetzungsmanagement', 'short_name' => 'UEM', 'sort' => 1, 'active' => true]);
        $this->workflow = Workflow::query()->create(['tenant_id' => $this->tenant->id, 'short_name' => 'T', 'name' => 'Test', 'active' => true, 'sort' => 1]);
    }

    /** @param list<array{0: int, 1: int}> $steps [lifecycle, duration] */
    private function steps(array $steps): void
    {
        foreach ($steps as $i => [$status, $duration]) {
            $this->steps[$i] = WorkflowStep::query()->create([
                'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'title' => 'S'.($i + 1),
                'sort' => $i + 1, 'lifecycle_status' => $status, 'duration_days' => $duration,
            ]);
        }
    }

    private function assignment(float $hours = 10): ProjectPerson
    {
        // Montag 1.3.2027 bis Freitag 12.3.2027 = 10 Arbeitstage
        $project = Project::query()->create([
            'tenant_id' => $this->tenant->id, 'source_pn' => '270001', 'title' => 'P', 'status' => 0,
            'workflow_id' => $this->workflow->id, 'start_date' => '2027-03-01', 'end_date' => '2027-03-12',
        ]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'last_name' => 'Tester', 'active' => true]);

        return ProjectPerson::query()->create([
            'tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'person_id' => $person->id,
            'function_group_id' => $this->group->id, 'planned_hours' => $hours,
        ])->load('project', 'person');
    }

    private function hours(ProjectPerson $assignment)
    {
        return (new ProjectPlanningCalculator)->plannedHoursByDay($assignment, CarbonImmutable::parse('2027-03-01'), CarbonImmutable::parse('2027-03-12'));
    }

    private function window(int $from, int $to): void
    {
        WorkflowGroupWindow::query()->create([
            'tenant_id' => $this->tenant->id, 'workflow_id' => $this->workflow->id, 'function_group_id' => $this->group->id,
            'from_step_id' => $this->steps[$from]->id, 'to_step_id' => $this->steps[$to]->id,
        ]);
    }

    public function test_without_a_window_hours_are_spread_evenly_over_the_whole_project(): void
    {
        $this->steps([[2, 5], [2, 5]]);
        $hours = $this->hours($this->assignment(10));

        $this->assertCount(10, $hours);
        $this->assertEqualsWithDelta(1.0, $hours->first(), 0.0001);
        $this->assertEqualsWithDelta(10.0, $hours->sum(), 0.0001);
    }

    public function test_window_limits_the_hours_to_the_days_of_its_steps(): void
    {
        $this->steps([[2, 5], [2, 5]]);
        $this->window(1, 1); // zweiter Schritt = zweite Woche
        $hours = $this->hours($this->assignment(10));

        $this->assertSame(['2027-03-08', '2027-03-09', '2027-03-10', '2027-03-11', '2027-03-12'], $hours->keys()->all());
        $this->assertEqualsWithDelta(2.0, $hours['2027-03-08'], 0.0001);
        $this->assertEqualsWithDelta(10.0, $hours->sum(), 0.0001);
    }

    public function test_missing_duration_counts_as_one_day_and_edge_steps_are_ignored(): void
    {
        // Planung (1), Arbeitsschritte: 9 Tage + ohne Angabe (= 1 Tag), Beendet (3) - zusammen 10 Tage = 10 Arbeitstage
        $this->steps([[1, 3], [2, 9], [2, 0], [3, 2]]);
        $this->window(2, 2);
        $hours = $this->hours($this->assignment(6));

        $this->assertSame(['2027-03-12'], $hours->keys()->all(), 'Der Schritt ohne Dauer bekommt genau einen Tag.');
        $this->assertEqualsWithDelta(6.0, $hours['2027-03-12'], 0.0001);
    }

    public function test_durations_that_exceed_the_project_are_compressed_and_flagged(): void
    {
        $this->steps([[2, 20], [2, 20]]);
        $this->window(0, 0);
        $assignment = $this->assignment(10);
        $hours = $this->hours($assignment);

        // 40 Gewichtstage auf 10 Arbeitstage: jeder Schritt bekommt 5 Tage
        $this->assertCount(5, $hours);
        $this->assertSame('2027-03-05', $hours->keys()->last());

        $notice = (new ProjectPlanningCalculator)->compressionNotice($assignment->project);
        $this->assertSame(40.0, $notice['sum']);
        $this->assertSame(10, $notice['available']);
    }

    public function test_no_notice_when_durations_fit_and_fallback_without_working_steps(): void
    {
        $this->steps([[2, 4], [2, 4]]);
        $assignment = $this->assignment(10);
        $this->assertNull((new ProjectPlanningCalculator)->compressionNotice($assignment->project));

        WorkflowStep::query()->where('workflow_id', $this->workflow->id)->update(['lifecycle_status' => 3]);
        $this->assertCount(10, (new ProjectPlanningCalculator)->plannedHoursByDay($assignment->fresh(['project', 'person']), CarbonImmutable::parse('2027-03-01'), CarbonImmutable::parse('2027-03-12')));
    }

    public function test_time_need_summary_lists_the_steps_and_compares_with_the_project_period(): void
    {
        // Zwei Schritte mit 8 und 6 Tagen, einer ohne Dauer (zählt 1), einer "In Planung" (zählt nicht): Summe 15 > 10 Arbeitstage
        $this->steps([[2, 8], [2, 6], [2, 0], [1, 40]]);
        $assignment = $this->assignment();
        $project = $assignment->project;

        $need = app(ProjectPlanningCalculator::class)->timeNeed($project);

        $this->assertSame('over', $need['state']);
        $this->assertSame(15, $need['sum']);
        $this->assertSame(10, $need['available']);
        $this->assertSame(0.67, $need['factor']);
        $this->assertSame(['S1', 'S2', 'S3'], array_column($need['steps'], 'title'));
        $this->assertSame([false, false, true], array_column($need['steps'], 'default_used'));

        // Ohne Workflow: Hinweis statt Zahlen
        $project->update(['workflow_id' => null]);
        $this->assertSame('no_workflow', app(ProjectPlanningCalculator::class)->timeNeed($project->fresh())['state']);
    }

    public function test_scaled_durations_are_whole_days_and_add_up_to_the_project_period(): void
    {
        // 8 + 6 Tage (+ ein Schritt ohne Dauer, der 1 Tag zählt und unverändert bleibt) auf 10 Arbeitstage:
        // 9 Tage verteilen: 5,14 / 3,86 -> 5 / 4; der Schritt ohne Dauer wird nicht zurückgegeben
        $this->steps([[2, 8], [2, 6], [2, 0]]);
        $project = $this->assignment()->project;
        $scaled = app(ProjectPlanningCalculator::class)->scaledDurations($project);
        $this->assertSame([5, 4], array_values($scaled));
        $this->assertSame(9, array_sum($scaled));
    }

    public function test_adjust_durations_saves_them_on_the_project_and_leaves_the_workflow_alone(): void
    {
        $this->steps([[2, 8], [2, 6], [2, 0]]);
        $project = $this->assignment()->project;
        foreach ($this->steps as $step) {
            \App\Models\ProjectWorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $step->id, 'sort' => $step->sort]);
        }
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);

        $this->actingAs($admin)->postJson(route('projekte.termine.adjust-durations', $project))->assertOk();

        $saved = \App\Models\ProjectWorkflowStep::query()->withoutGlobalScope('tenant')->where('project_id', $project->id)->orderBy('sort')->pluck('duration_days')->all();
        $this->assertSame([5, 4, null], $saved);   // der Schritt ohne Dauer bleibt unberührt
        $this->assertSame([8, 6, 0], collect($this->steps)->map(fn ($step) => (int) $step->fresh()->duration_days)->all());

        // Danach passen Bedarf und Zeitraum zusammen
        $need = app(ProjectPlanningCalculator::class)->timeNeed($project->fresh());
        $this->assertSame('ok', $need['state']);
        $this->assertSame(10, $need['sum']);

        // Ohne Recht keine Anpassung
        $plain = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
        $this->actingAs($plain)->postJson(route('projekte.termine.adjust-durations', $project))->assertForbidden();
    }

    public function test_calculated_end_dates_follow_the_durations_on_working_days(): void
    {
        // Start Montag 1.3.2027; Schritte: 3 Tage, 4 Tage (über das Wochenende), 0 -> zählt 1
        $this->steps([[2, 3], [2, 4], [2, 0], [1, 9]]);
        $project = $this->assignment()->project;

        $ends = app(ProjectPlanningCalculator::class)->stepEndDates($project);

        $this->assertSame('2027-03-03', $ends[$this->steps[0]->id]->toDateString());   // Mo-Mi
        $this->assertSame('2027-03-09', $ends[$this->steps[1]->id]->toDateString());   // Do, Fr, Mo, Di
        $this->assertSame('2027-03-10', $ends[$this->steps[2]->id]->toDateString());   // Mi (Mindestwert 1 Tag)
        $this->assertArrayNotHasKey($this->steps[3]->id, $ends);                        // nicht "In Bearbeitung"
    }

    public function test_period_chart_shows_buffer_or_overflow_with_suggested_dates(): void
    {
        // Zeitraum Mo 1.3. bis Fr 12.3.2027 = 10 Arbeitstage
        $this->steps([[2, 4], [2, 3]]);
        $project = $this->assignment()->project;
        $chart = app(ProjectPlanningCalculator::class)->periodChart($project);

        // Bedarf 7 < 10: Puffer 3 AT; Ende bei festem Start Dienstag 9.3., Start bei festem Ende Donnerstag 4.3.
        $this->assertSame(['buffer', 7, 10, 3, '2027-03-09', '2027-03-04'], [$chart['period']['mode'], $chart['period']['need'], $chart['period']['available'], $chart['period']['diff'], $chart['period']['new_end'], $chart['period']['new_start']]);
        $this->assertSame([4, 3], array_column($chart['steps'], 'days'));   // Dauern bleiben unverändert

        // Bedarf 13 > 10: Kalender und Arbeitstage werden über das Projektende hinaus verlängert
        $this->steps[0]->update(['duration_days' => 8]);
        $this->steps[1]->update(['duration_days' => 5]);
        $chart = app(ProjectPlanningCalculator::class)->periodChart($project->fresh());
        $this->assertSame('overflow', $chart['period']['mode']);
        $this->assertCount(13, $chart['workdays']);
        $this->assertSame('2027-03-17', end($chart['calendar']));
        $this->assertSame('2027-03-17', $chart['period']['new_end']);
        $this->assertSame('2027-02-24', $chart['period']['new_start']);   // 3 Arbeitstage vor Mo 1.3.

        // passt genau
        $this->steps[1]->update(['duration_days' => 2]);
        $this->assertSame('match', app(ProjectPlanningCalculator::class)->periodChart($project->fresh())['period']['mode']);
    }

    public function test_set_period_moves_project_dates_or_the_marked_schedule_step(): void
    {
        $this->steps([[2, 4], [2, 3]]);
        $project = $this->assignment()->project;
        $admin = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']);

        $this->actingAs($admin)->postJson(route('projekte.termine.set-period', $project), ['side' => 'end', 'date' => '2027-03-09'])->assertOk();
        $this->assertSame('2027-03-09', $project->fresh()->end_date->toDateString());

        // Start darf nicht hinter dem Ende liegen
        $this->actingAs($admin)->postJson(route('projekte.termine.set-period', $project), ['side' => 'start', 'date' => '2027-03-20'])->assertStatus(422);

        // Ist ein Schritt als Ende markiert, ist er die Quelle: sein Termin wird gesetzt, das Projektende folgt
        $this->steps[1]->update(['has_due_date' => true]);
        $pws = \App\Models\ProjectWorkflowStep::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'workflow_step_id' => $this->steps[1]->id, 'sort' => 2, 'is_end' => true]);
        $this->actingAs($admin)->postJson(route('projekte.termine.set-period', $project), ['side' => 'end', 'date' => '2027-03-11'])->assertOk();
        $this->assertSame('2027-03-11', $pws->fresh()->due_date->toDateString());
        $this->assertSame('2027-03-11', $project->fresh()->end_date->toDateString());

        $plain = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']);
        $this->actingAs($plain)->postJson(route('projekte.termine.set-period', $project), ['side' => 'end', 'date' => '2027-03-12'])->assertForbidden();
    }
}
