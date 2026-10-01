<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FunctionGroupCentralizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_catalog_and_customer_availability_matrix_control_customer_groups(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Kunde']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');

        $user = User::factory()->create(['tenant_id' => $home->id, 'role' => 'super_admin']);
        $available = FunctionGroup::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $home->id,
            'name' => 'Technische Redaktion',
            'short_name' => 'TR',
        ]);
        $hidden = FunctionGroup::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $home->id,
            'name' => 'Technische Illustration',
            'short_name' => 'TI',
        ]);
        $person = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $home->id,
            'first_name' => 'Tina',
            'last_name' => 'Redaktion',
            'active' => true,
        ]);
        $customerPerson = Person::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $customer->id,
            'first_name' => 'Karla',
            'last_name' => 'Kunde',
            'active' => true,
        ]);
        DB::table('person_tenant')->insert(['person_id' => $person->id, 'tenant_id' => $customer->id]);
        DB::table('function_group_member')->insert([
            'tenant_id' => $home->id,
            'function_group_id' => $available->id,
            'person_id' => $person->id,
        ]);

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $home->id])
            ->post(route('admin.function-groups.availability.update'), [
                'availability' => [$available->id => [$customer->id]],
            ])
            ->assertRedirect(route('admin.function-groups'));

        $this->withSession(['active_tenant_id' => $customer->id])
            ->get(route('admin.function-groups'))
            ->assertOk()
            ->assertSee('Technische Redaktion')
            ->assertDontSee('Technische Illustration');

        $this->post(route('admin.function-groups.members.update', $available), [
            'person_ids' => [$customerPerson->id],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$person->id, $customerPerson->id],
            $available->eligibleMembersInTenant($customer->id, 'super_admin')->pluck('id')->all(),
        );
        $this->assertTrue($available->isAvailableForTenant($customer->id));
        $this->assertFalse($hidden->isAvailableForTenant($customer->id));
    }
}
