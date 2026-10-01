<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_without_first_name_can_be_opened(): void
    {
        $tenant = Tenant::query()->firstOrFail();
        $tenant->update(['is_home_tenant' => true]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);
        $person = Person::query()->create([
            'tenant_id' => $tenant->id,
            'first_name' => null,
            'last_name' => 'Ext. ÜbersetzungsDL',
            'active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id])
            ->withHeader('X-Overlay', '1')
            ->get(route('admin.personen.edit', $person))
            ->assertOk()
            ->assertSee('Ext. ÜbersetzungsDL');
    }
}
