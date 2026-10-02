<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanningWorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_people_view_contains_every_visible_working_hours_series(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $people = collect(['Dreißig Eins', 'Dreißig Zwei'])->map(function (string $lastName) use ($tenant) {
            $person = Person::query()->create([
                'tenant_id' => $tenant->id,
                'last_name' => $lastName,
                'active' => true,
                'resource_planning' => true,
            ]);
            User::factory()->create(['tenant_id' => $tenant->id, 'person_id' => $person->id]);
            DB::table('person_weekly_hours')->insert([
                'tenant_id' => $tenant->id,
                'person_id' => $person->id,
                'hours' => 30,
                'valid_from' => null,
                'valid_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $person;
        });
        $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'super_admin']);

        $response = $this->actingAs($viewer)->get(route('planung.arbeitszeit', [
            'year' => 2026,
            'person' => 'all',
        ]))->assertOk()
            ->assertSee('<option value="all" selected>– alle –</option>', false)
            ->assertSee('display: showAllPeople', false)
            ->assertSee("sameValueNames.join(', ')", false);

        $this->assertTrue($response->viewData('showAllPeople'));
        $this->assertSame(
            $people->pluck('id')->sort()->values()->all(),
            $response->viewData('datasets')->pluck('personId')->sort()->values()->all(),
        );
    }
}
