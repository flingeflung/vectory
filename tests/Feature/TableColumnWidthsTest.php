<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTablePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Persönliche Spaltenbreiten auch für Personentabelle und Planung > Stunden (Ralf, 2026-10-09). */
class TableColumnWidthsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::query()->firstOrFail();
        $this->admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);
        $person = Person::query()->create(['tenant_id' => $tenant->id, 'last_name' => 'Breit', 'active' => true, 'resource_planning' => true]);
        DB::table('person_weekly_hours')->insert(['tenant_id' => $tenant->id, 'person_id' => $person->id, 'hours' => 40, 'valid_from' => null, 'valid_to' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_tables_are_wired_for_column_resizing(): void
    {
        $this->actingAs($this->admin)->get(route('admin.personen'))->assertOk()
            ->assertSee("columnResize('personen'", false)->assertSee('data-col="name"', false);
        $this->actingAs($this->admin)->get(route('planung.stunden', ['year' => 2026]))->assertOk()
            ->assertSee("columnResize('stunden'", false)->assertSee('data-col="annual_hours"', false);
    }

    public function test_widths_are_saved_per_user_and_table_and_can_be_reset(): void
    {
        foreach (['personen', 'stunden'] as $key) {
            $this->actingAs($this->admin)->putJson(route('tabellenbreiten.update', $key), ['widths' => ['name' => 220]])->assertNoContent();
            $this->assertSame(['name' => 220], UserTablePreference::widthsFor($this->admin->id, $key));
        }

        $this->assertSame(['name' => 220], $this->actingAs($this->admin)->get(route('admin.personen'))->viewData('columnWidths'));

        $this->actingAs($this->admin)->deleteJson(route('tabellenbreiten.destroy', 'personen'))->assertNoContent();
        $this->assertSame([], UserTablePreference::widthsFor($this->admin->id, 'personen'));
        $this->assertSame(['name' => 220], UserTablePreference::widthsFor($this->admin->id, 'stunden'));

        $this->actingAs($this->admin)->putJson(route('tabellenbreiten.update', 'unbekannt'), ['widths' => ['name' => 220]])->assertNotFound();
    }
}
