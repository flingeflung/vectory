<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlanningBaseLoad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Planung > Stunden > Grafik: ein Balken je Person, Jahresstunden = Grundlast (unten) + Projektstunden (Ralf, 2026-10-09). */
class PlanningHoursChartTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->viewer = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'super_admin']);
    }

    private function person(string $lastName, float $weeklyHours, string $shortName = ''): Person
    {
        $person = Person::query()->create(['tenant_id' => $this->tenant->id, 'last_name' => $lastName, 'short_name' => $shortName ?: null, 'active' => true, 'resource_planning' => true]);
        User::factory()->create(['tenant_id' => $this->tenant->id, 'person_id' => $person->id]);
        DB::table('person_weekly_hours')->insert([
            'tenant_id' => $this->tenant->id, 'person_id' => $person->id, 'hours' => $weeklyHours,
            'valid_from' => null, 'valid_to' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $person;
    }

    public function test_both_views_are_offered_and_the_table_stays_the_default(): void
    {
        $this->person('Tabellenperson', 40);

        $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026]))->assertOk()
            ->assertSee('Tabellenperson')->assertSee('>Tabelle<', false)->assertSee('>Grafik<', false)->assertSee('Jahresstd.')->assertDontSee('Jahresstunden gesamt');
    }

    public function test_chart_shows_one_bar_per_person_with_the_table_values(): void
    {
        $a = $this->person('Anders', 40, 'AND');
        $this->person('Berger', 20);

        $response = $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'grafik']))->assertOk();
        $rows = collect($response->viewData('rows'));
        $this->assertCount(2, $rows);

        $row = $rows->firstWhere('personId', $a->id);
        // Jahresstunden = Grundlast + Projektstunden (Projektstunden sind der Rest)
        $this->assertEqualsWithDelta($row['jahresstd'], $row['baseLoad'] + $row['projectHours'], 0.001);
        $this->assertGreaterThan(0, $row['jahresstd']);

        $response->assertSee('Jahresstunden gesamt')->assertSee('title="Anders, ', false)->assertSee('title="Berger, ', false)
            ->assertSee('AND')       // Kürzel unter dem Balken
            ->assertSee('Berger')    // ohne Kürzel der Nachname
            ->assertDontSee('Jahresstd.');   // keine Tabelle in der Grafik-Ansicht
    }

    public function test_axis_maximum_covers_the_tallest_bar_and_ticks_are_few(): void
    {
        $this->person('Hoch', 40);
        $response = $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'grafik']))->assertOk();

        $chart = $response->viewData('chart');
        $highest = (float) collect($response->viewData('rows'))->max('jahresstd');
        $this->assertGreaterThanOrEqual($highest, $chart['max']);
        $this->assertLessThanOrEqual(7, count($chart['ticks']));
        $this->assertSame(0.0, $chart['ticks'][0]);
        $this->assertEqualsWithDelta($chart['max'], end($chart['ticks']), 0.001);
    }

    public function test_base_load_larger_than_the_annual_hours_is_marked(): void
    {
        $person = $this->person('Ueberlastet', 10);
        PlanningBaseLoad::query()->create(['tenant_id' => $this->tenant->id, 'year' => 2026, 'name' => 'Riesig', 'calculation_type' => 'yearly', 'value' => 5000, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31']);

        $response = $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'grafik']))->assertOk();

        $row = collect($response->viewData('rows'))->firstWhere('personId', $person->id);
        $this->assertLessThan(0, $row['projectHours']);
        $response->assertSee('Die Grundlast übersteigt die Jahresstunden.')->assertSee('bg-red-400', false);
    }

    public function test_the_view_choice_survives_sorting_and_the_year_form(): void
    {
        $this->person('Eins', 40);
        $html = $this->actingAs($this->viewer)->get(route('planung.stunden', ['year' => 2026, 'ansicht' => 'grafik', 'sort' => 'annual_hours']))->assertOk()->getContent();

        $this->assertStringContainsString('name="ansicht" value="grafik"', $html);
        $this->assertStringContainsString('ansicht=grafik', $html);
    }
}
