<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RightsAdminRoleNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_person_without_permission_set_shows_the_admin_note(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Maschinen', 'short_name' => 'MA']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');

        $adminPerson = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $home->id, 'first_name' => 'Ada', 'last_name' => 'Admin', 'active' => true]);
        User::factory()->create(['tenant_id' => $home->id, 'role' => 'central_admin', 'person_id' => $adminPerson->id]);
        $plainPerson = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $home->id, 'first_name' => 'Uwe', 'last_name' => 'Normal', 'active' => true]);
        User::factory()->create(['tenant_id' => $home->id, 'role' => 'user', 'person_id' => $plainPerson->id]);
        DB::table('person_tenant')->insert([['person_id' => $adminPerson->id, 'tenant_id' => $customer->id], ['person_id' => $plainPerson->id, 'tenant_id' => $customer->id]]);

        $viewer = User::factory()->create(['tenant_id' => $home->id, 'role' => 'central_admin']);
        $this->actingAs($viewer)->withSession(['active_tenant_id' => $customer->id]);

        $this->get(route('admin.rechte', ['person' => $adminPerson->id]))->assertOk()
            ->assertSee('Besitzt die Rolle Zentral-Administrator; alle Rechte sind auch ohne Rechteset automatisch vorhanden.')
            ->assertDontSee('Aktuelles Rechte-Set');

        $this->get(route('admin.rechte', ['person' => $plainPerson->id]))->assertOk()
            ->assertSee('Aktuelles Rechte-Set')
            ->assertDontSee('Besitzt die Rolle');
    }
}
