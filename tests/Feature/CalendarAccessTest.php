<?php

namespace Tests\Feature;

use App\Models\CalendarEntry;
use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAccessTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSee("x-show=\"type === 'absence'\"", false);
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
}
