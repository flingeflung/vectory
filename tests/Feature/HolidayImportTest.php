<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_import_holidays_without_overwriting_existing_records(): void
    {
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $target = Tenant::query()->firstOrFail();
        $target->update(['is_home_tenant' => true]);
        $source = Tenant::query()->create(['name' => 'Quellkunde', 'short_name' => 'QK']);
        $user = User::factory()->create([
            'tenant_id' => $target->id,
            'role' => 'super_admin',
        ]);

        Holiday::withoutGlobalScope('tenant')->create([
            'tenant_id' => $source->id,
            'name' => 'Neujahr',
            'date' => '2027-01-01',
            'weekday' => 5,
            'remarks' => 'Quelle',
            'active' => true,
        ]);
        Holiday::withoutGlobalScope('tenant')->create([
            'tenant_id' => $target->id,
            'name' => 'Neujahr',
            'date' => '2027-01-01',
            'weekday' => 5,
            'remarks' => 'Lokale Anpassung',
            'active' => false,
        ]);
        Holiday::withoutGlobalScope('tenant')->create([
            'tenant_id' => $source->id,
            'name' => 'Tag der Arbeit',
            'date' => '2027-05-01',
            'weekday' => 6,
            'remarks' => null,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.feiertage', ['year' => 2027]))
            ->assertOk()
            ->assertSee(__('Von anderem Kunden importieren'));

        $this->post(route('admin.feiertage.uebernehmen'), [
            'source_tenant_id' => $source->id,
            'year' => 2027,
        ])->assertRedirect(route('admin.feiertage', ['year' => 2027]));

        $this->assertDatabaseHas('holidays', [
            'tenant_id' => $target->id,
            'name' => 'Tag der Arbeit',
            'date' => '2027-05-01',
            'active' => true,
        ]);
        $this->assertDatabaseHas('holidays', [
            'tenant_id' => $target->id,
            'name' => 'Neujahr',
            'remarks' => 'Lokale Anpassung',
            'active' => false,
        ]);
        $this->assertSame(2, Holiday::withoutGlobalScope('tenant')->where('tenant_id', $target->id)->count());

        $this->post(route('admin.feiertage.uebernehmen'), [
            'source_tenant_id' => $source->id,
            'year' => 2027,
        ])->assertRedirect(route('admin.feiertage', ['year' => 2027]));

        $this->assertSame(2, Holiday::withoutGlobalScope('tenant')->where('tenant_id', $target->id)->count());
    }
}
