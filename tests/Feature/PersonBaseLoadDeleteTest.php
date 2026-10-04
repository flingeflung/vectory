<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PlanningPersonBaseLoad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonBaseLoadDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::query()->firstOrFail();
        $this->person = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id, 'last_name' => 'Muster', 'first_name' => 'Anna', 'active' => true, 'resource_planning' => true,
        ]);
        User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'person_id' => $this->person->id]);
    }

    private function record(string $name = 'Weiterbildung'): PlanningPersonBaseLoad
    {
        return PlanningPersonBaseLoad::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id, 'person_id' => $this->person->id, 'planning_base_load_id' => null, 'year' => 2027,
            'name' => $name, 'calculation_type' => 'weekly', 'value' => 2, 'valid_from' => '2027-01-01', 'valid_to' => '2027-12-31',
        ]);
    }

    public function test_a_single_record_can_be_deleted_and_others_stay(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']));
        $weiterbildung = $this->record('Weiterbildung');
        $meetings = $this->record('Meetings');

        $this->call('DELETE', route('planung.grundlast-person.destroy', $weiterbildung), ['year' => 2027, 'person' => $this->person->id])
            ->assertRedirect(route('planung.grundlast-person', ['year' => 2027, 'person' => $this->person->id]));

        $this->assertModelMissing($weiterbildung);
        $this->assertModelExists($meetings);
    }

    public function test_without_the_planning_right_nothing_is_deleted(): void
    {
        $record = $this->record();
        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'user']));

        $this->call('DELETE', route('planung.grundlast-person.destroy', $record), ['year' => 2027, 'person' => $this->person->id]);

        $this->assertModelExists($record);
    }

    public function test_a_record_of_another_person_or_year_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']));
        $record = $this->record();

        $this->call('DELETE', route('planung.grundlast-person.destroy', $record), ['year' => 2028, 'person' => $this->person->id]);

        $this->assertModelExists($record);
    }

    public function test_the_page_offers_a_delete_button_per_record(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'organization_admin']));
        $record = $this->record('Weiterbildung');

        $this->get(route('planung.grundlast-person', ['year' => 2027, 'person' => $this->person->id]))
            ->assertOk()
            ->assertSee('delete-person-base-load-'.$record->id)
            ->assertSee('Datensatz löschen');
    }
}
