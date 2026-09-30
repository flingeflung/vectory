<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_remembers_the_last_displayed_month_for_each_user(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);

        $this->actingAs($user)
            ->get(route('kalender', ['year' => 2026, 'month' => 11]))
            ->assertOk()
            ->assertViewHas('year', 2026)
            ->assertViewHas('month', 11);

        $this->assertSame(
            ['year' => 2026, 'month' => 11],
            UserPreference::configFor($user->id, UserPreference::CALENDAR),
        );

        $this->get(route('kalender'))
            ->assertOk()
            ->assertViewHas('year', 2026)
            ->assertViewHas('month', 11);
    }

    public function test_superadmin_can_open_calendar_without_calendar_enabled_person(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['is_home_tenant' => true]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
            'person_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('kalender'))
            ->assertOk()
            ->assertSee(__('Kalender'))
            ->assertSee(__('Erläuterung (optional)'));
    }

    public function test_superadmin_can_create_a_tracked_entry_for_another_visible_person(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['is_home_tenant' => true]);
        $person = Person::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Patricia',
            'last_name' => 'Planung',
            'calendar_enabled' => true,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
            'person_id' => null,
        ]);

        $this->assertTrue($user->can('calendar.entries.manage_others'));
        $this->actingAs($user)
            ->get(route('kalender', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('Planung');

        $this->post(route('kalender.eintraege.store'), [
            'person_id' => $person->id,
            'type' => CalendarEntry::TYPE_ABSENCE,
            'starts_on' => '2026-10-05',
            'ends_on' => '2026-10-05',
            'note' => 'Urlaub',
            'return_year' => 2026,
            'return_month' => 10,
        ])->assertRedirect(route('kalender', ['year' => 2026, 'month' => 10]));

        $this->assertDatabaseHas('calendar_entries', [
            'person_id' => $person->id,
            'created_by_user_id' => $user->id,
            'note' => 'Urlaub',
        ]);

        $this->get(route('kalender', ['year' => 2026, 'month' => 10]))
            ->assertOk()
            ->assertSee('Angelegt von '.$user->name, false);
    }

    public function test_explanation_is_stored_for_mobile_office_entries(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['is_home_tenant' => true]);
        $person = Person::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Mona',
            'last_name' => 'Mobil',
            'calendar_enabled' => true,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);

        $this->actingAs($user)->post(route('kalender.eintraege.store'), [
            'person_id' => $person->id,
            'type' => CalendarEntry::TYPE_MOBILE_OFFICE,
            'starts_on' => '2026-10-06',
            'ends_on' => '2026-10-06',
            'note' => 'vormittags',
            'return_year' => 2026,
            'return_month' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('calendar_entries', [
            'person_id' => $person->id,
            'type' => CalendarEntry::TYPE_MOBILE_OFFICE,
            'note' => 'vormittags',
        ]);
    }
}
