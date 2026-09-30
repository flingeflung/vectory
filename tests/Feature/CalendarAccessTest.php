<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_calendar_without_calendar_enabled_person(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Standard',
            'short_name' => 'STD',
            'is_home_tenant' => true,
        ]);
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
}
