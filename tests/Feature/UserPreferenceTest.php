<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPreference;
use App\Support\DashboardTileCatalog;
use App\Support\PersonTableColumnCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_column_order_is_stored_centrally_and_completed_with_new_columns(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)->putJson(route('admin.personen.tabellenspalten.update'), [
            'columns' => ['email', 'name', 'department'],
        ])->assertNoContent();

        $preference = UserPreference::query()
            ->where('user_id', $user->id)
            ->where('key', UserPreference::PEOPLE_TABLE)
            ->firstOrFail();

        $this->assertSame(['email', 'name', 'department'], array_slice($preference->config['column_order'], 0, 3));
        $this->assertSame(
            ['email', 'name', 'department'],
            array_slice(array_column(PersonTableColumnCatalog::orderedFor($user, false), 'key'), 0, 3),
        );
        $this->assertContains('resource_planning', $preference->config['column_order']);
        $this->assertContains('calendar', $preference->config['column_order']);

        $this->get(route('admin.personen'))
            ->assertOk()
            ->assertSeeInOrder([
                'x-sort:item="email"',
                'x-sort:item="name"',
                'x-sort:item="department"',
            ], false);
    }

    public function test_dashboard_layout_uses_the_central_preferences_table(): void
    {
        $user = User::factory()->create();

        DashboardTileCatalog::persistActiveFor($user, ['favorites', 'recent_projects']);

        $this->assertSame(['favorites', 'recent_projects'], DashboardTileCatalog::activeFor($user));
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'key' => UserPreference::DASHBOARD,
        ]);
    }
}
