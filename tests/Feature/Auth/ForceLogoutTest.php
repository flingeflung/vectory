<?php

namespace Tests\Feature\Auth;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Person sofort abmelden, optional zusätzlich deaktivieren (Ralf, 2026-10-08). */
class ForceLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function setUpPerson(): array
    {
        $tenant = Tenant::query()->firstOrFail();
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'organization_admin']);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'first_name' => 'Karl', 'last_name' => 'Kick', 'email' => 'kick@example.test', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user', 'person_id' => $person->id]);
        DB::table('sessions')->insert(['id' => 'sess-kick', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        return [$admin, $person, $user];
    }

    public function test_sessions_are_ended_without_deactivating(): void
    {
        [$admin, $person, $user] = $this->setUpPerson();

        $this->actingAs($admin)->post(route('admin.personen.force-logout', $person))->assertRedirect();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue((bool) $person->fresh()->active);
    }

    public function test_a_normal_user_may_not_force_logout(): void
    {
        [, $person, $user] = $this->setUpPerson();

        $this->actingAs($user)->post(route('admin.personen.force-logout', $person));

        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue((bool) $person->fresh()->active);
    }

    public function test_password_change_ends_other_sessions_but_keeps_the_current_one(): void
    {
        [, , $user] = $this->setUpPerson();
        $user->update(['password' => bcrypt('AltesPasswort')]);

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'AltesPasswort',
            'password' => 'NeuesPasswort1',
            'password_confirmation' => 'NeuesPasswort1',
        ]);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->where('id', 'sess-kick')->count());
    }

    public function test_admin_password_reset_ends_all_sessions(): void
    {
        [$admin, $person, $user] = $this->setUpPerson();

        $this->actingAs($admin)->post(route('admin.personen.password.reset', $person), ['password' => 'NeuesPasswort1', 'password_confirmation' => 'NeuesPasswort1']);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}
