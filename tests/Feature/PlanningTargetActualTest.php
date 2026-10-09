<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\ProjectPerson;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlanningTargetActual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Planung > Stunden > Soll-Ist: verfügbare Stunden gegen verplante, je Monat und gesamt (Ralf, 2026-10-09). */
class PlanningTargetActualTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $viewer;

    private FunctionGroup $group;

    private int $pnCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'super_admin']);
        $this->group = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $this->tenant->id, 'name' => 'TR', 'short_name' => 'TR', 'sort' => 1, 'active' => true]);
    }

    private function person(string $name, float $weeklyHours = 40): Person
    {
        $person = Person::query()->create(['tenant_id' => $this->tenant->id, 'last_name' => $name, 'active' => true, 'resource_planning' => true]);
        User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id]);
        DB::table('person_weekly_hours')->insert([
            'tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'hours' => $weeklyHours,
            'valid_from' => null, 'valid_to' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $person;
    }

    private function project(string $start, string $end, int $status = 1, bool $archived = false): Project
    {
        return Project::query()->create(['tenant_id' => $this->tenant->id, 'source_pn' => '29'.str_pad((string) ++$this->pnCounter, 4, '0', STR_PAD_LEFT), 'title' => 'P', 'status' => $status, 'archived' => $archived, 'start_date' => $start, 'end_date' => $end]);
    }

    private function assign(Project $project, Person $person, float $hours): ProjectPerson
    {
        return ProjectPerson::query()->create(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'function_group_id' => $this->group->id, 'person_id' => $person->id, 'planned_hours' => $hours]);
    }

    private function compute(int $year = 2027, ?string $from = null): array
    {
        $people = Person::query()->withoutGlobalScopes()->where('resource_planning', true)->with(['weeklyHours', 'calendarEntries'])->get();

        return app(PlanningTargetActual::class)->forYear($people, CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 12, 31), $this->tenant->id, $from ? CarbonImmutable::parse($from) : null);
    }

    public function test_available_hours_are_the_work_hours_without_weekends_and_planned_hours_land_in_their_months(): void
    {
        $person = $this->person('Anders', 40);
        // März 2027: Mo 1.3. bis Fr 5.3. = 5 Arbeitstage = 40 h, davon 40 h verplant (genau eine Woche, 5 Arbeitstage)
        $this->assign($this->project('2027-03-01', '2027-03-05'), $person, 40);

        $result = $this->compute();

        $march = $result['months'][2];
        $this->assertSame(3, $march['month']);
        $this->assertEqualsWithDelta(40.0, $march['planned'], 0.01);
        // März 2027 hat 23 Arbeitstage (Mo-Fr) -> 184 h verfügbar
        $this->assertEqualsWithDelta(184.0, $march['available'], 0.01);
        $this->assertEqualsWithDelta(144.0, $march['remaining'], 0.01);
        $this->assertEqualsWithDelta(40.0, $result['planned'], 0.01);
        $this->assertEqualsWithDelta($result['available'] - 40.0, $result['remaining'], 0.01);
        $this->assertEqualsWithDelta(40.0 / $result['available'] * 100, $result['utilization'], 0.1);
        $this->assertSame(0.0, $result['months'][0]['planned']);
    }

    public function test_a_project_across_two_months_is_split_by_workdays_and_calendar_absences_reduce_the_capacity(): void
    {
        $person = $this->person('Berger', 40);
        // 29.3. (Mo) bis 2.4. (Fr) 2027: 3 Arbeitstage im März (29., 30., 31.), 2 im April (1., 2.) -> 100 h gleichmäßig = 60 / 40
        $this->assign($this->project('2027-03-29', '2027-04-02'), $person, 100);
        CalendarEntry::query()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'type' => CalendarEntry::TYPE_ABSENCE, 'starts_on' => '2027-04-05', 'ends_on' => '2027-04-09', 'title' => 'Urlaub']);

        $result = $this->compute();

        $this->assertEqualsWithDelta(60.0, $result['months'][2]['planned'], 0.01);
        $this->assertEqualsWithDelta(40.0, $result['months'][3]['planned'], 0.01);
        // April 2027: 22 Arbeitstage, davon eine Urlaubswoche (5) -> 17 Tage = 136 h
        $this->assertEqualsWithDelta(136.0, $result['months'][3]['available'], 0.01);
    }

    public function test_only_planned_and_in_progress_projects_count_and_overbooking_is_negative(): void
    {
        $person = $this->person('Citron', 40);
        $this->assign($this->project('2027-03-01', '2027-03-05', 0), $person, 10);                  // Geplant: zählt
        $this->assign($this->project('2027-03-01', '2027-03-05', 1), $person, 10);                  // In Bearbeitung: zählt
        $this->assign($this->project('2027-03-01', '2027-03-05', 2), $person, 500);                 // Beendet: zählt nicht
        $this->assign($this->project('2027-03-01', '2027-03-05', 3), $person, 500);                 // Verworfen: zählt nicht
        $this->assign($this->project('2027-03-01', '2027-03-05', 1, true), $person, 500);          // archiviert: zählt nicht

        $this->assertEqualsWithDelta(20.0, $this->compute()['months'][2]['planned'], 0.01);

        // Überplanung: weit mehr Stunden in einer Woche als verfügbar
        $this->assign($this->project('2027-03-08', '2027-03-12'), $person, 400);
        $march = $this->compute()['months'][2];
        $this->assertEqualsWithDelta(420.0, $march['planned'], 0.01);
        $this->assertEqualsWithDelta($march['available'] - 420.0, $march['remaining'], 0.01);
    }

    public function test_the_third_tab_shows_totals_months_and_the_undistributed_hint(): void
    {
        // Die Seite bietet nur Jahre an, für die Daten vorliegen (mindestens das laufende): deshalb 2026
        $person = $this->person('Dorn', 40);
        $project = $this->project('2026-03-02', '2026-03-06');
        $this->assign($project, $person, 20);
        // Planstunden der Funktionsgruppe 50 h, davon 20 h verteilt -> 30 h noch nicht verteilt
        DB::table('project_function_group_hours')->insert(['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'function_group_id' => $this->group->id, 'planned_hours' => 50, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'soll-ist', 'ab' => '2026-01-01']))->assertOk()
            ->assertSee('Verfügbar für Projekte')->assertSee('Noch übrig')->assertSee('Auslastung')
            ->assertSee('Noch nicht auf Personen verteilt: 30,0 Std. in 1 Projekten')
            ->assertSee('>Soll-Ist<', false)->assertSee('>Tabelle<', false)->assertSee('>Grafik<', false);
    }

    public function test_only_the_time_after_the_cutoff_counts_and_a_running_project_is_cut(): void
    {
        $person = $this->person('Ernst', 40);
        // 1.3.-31.3.2027 = 23 Arbeitstage, 230 h; ab 16.3. bleiben 12 Arbeitstage (16.-31.3.) = 120 h
        $this->assign($this->project('2027-03-01', '2027-03-31'), $person, 230);

        $full = $this->compute();
        $cut = $this->compute(2027, '2027-03-16');

        $this->assertEqualsWithDelta(230.0, $full['planned'], 0.01);
        $this->assertEqualsWithDelta(120.0, $cut['planned'], 0.01);
        $this->assertTrue($cut['months'][0]['past']);
        $this->assertTrue($cut['months'][1]['past']);
        $this->assertFalse($cut['months'][2]['past']);
        $this->assertSame(0.0, $cut['months'][0]['available']);
        // März ab 16.3.: 12 Arbeitstage = 96 h verfügbar; die Jahreskapazität bleibt als Orientierung erhalten
        $this->assertEqualsWithDelta(96.0, $cut['months'][2]['available'], 0.01);
        $this->assertEqualsWithDelta($full['available'], $cut['yearAvailable'], 0.01);
    }

    public function test_the_outlook_shows_the_cutoff_and_offers_a_date_field(): void
    {
        $this->person('Fischer', 40);

        $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'soll-ist', 'ab' => '2026-12-31']))->assertOk()->assertSee('Ausblick ab 31.12.2026');
        $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'soll-ist']))->assertOk()->assertSee('name="ab"', false);
    }
}
