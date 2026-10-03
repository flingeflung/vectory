<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Market;
use App\Models\Person;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttributeColumnManager;
use App\Services\TenantConfigCloner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantPurgeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $home;

    private Tenant $test;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::forgetInactiveCache();
        $this->home = Tenant::query()->firstOrFail();
        $this->home->update(['is_home_tenant' => true]);
        $this->test = Tenant::query()->create(['name' => 'Testkunde', 'short_name' => 'TK']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');
        $this->actingAs(User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'super_admin']));
    }

    protected function tearDown(): void
    {
        Tenant::forgetInactiveCache();
        parent::tearDown();
    }

    private function fill(Tenant $tenant, string $attributeKey): Attribute
    {
        app(TenantConfigCloner::class)->seedSystemAttributes($tenant);
        $attribute = Attribute::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'section' => 'details', 'key' => $attributeKey, 'label' => $attributeKey,
            'data_type' => Attribute::DATA_TYPE_TEXT, 'sort' => 1,
        ]);
        app(AttributeColumnManager::class)->ensureColumn($attribute);
        Market::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'country_iso' => 'FR', 'country_name' => 'Frankreich', 'country_short_name' => 'FR',
            'language_code' => 'fr', 'language_name' => 'FR', 'no_translation' => false, 'sort' => 1,
        ]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'last_name' => 'Tester', 'active' => true]);
        Project::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $tenant->id, 'source_pn' => '270001', 'title' => 'Testprojekt', 'status' => 0]);
        User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'user', 'person_id' => $person->id]);

        return $attribute;
    }

    private function purge(string $typedName)
    {
        return $this->delete(route('admin.kunden.purge', $this->test), ['confirm_name' => $typedName]);
    }

    public function test_purge_removes_everything_of_the_organization_including_its_unused_column(): void
    {
        $this->fill($this->test, 'nur_im_test');
        $column = 'attributes_nur_im_test';
        $this->assertTrue(Schema::hasColumn('projects', $column));

        $this->purge('Testkunde')->assertRedirect(route('admin.kunden'));

        $this->assertDatabaseMissing('tenants', ['id' => $this->test->id]);
        $leftovers = [];
        foreach (DB::select('SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = "tenant_id"') as $row) {
            if (DB::table($row->t)->where('tenant_id', $this->test->id)->exists()) {
                $leftovers[] = $row->t;
            }
        }
        $this->assertSame([], $leftovers);
        $this->assertSame(0, DB::table('users')->where('role', 'user')->count());
        $this->assertFalse(Schema::hasColumn('projects', $column), 'Verwaiste Attribut-Spalte muss weg sein.');
    }

    public function test_a_column_that_another_organization_still_uses_stays(): void
    {
        $this->fill($this->test, 'gemeinsam');
        $other = Tenant::query()->create(['name' => 'Anderer', 'short_name' => 'AN']);
        $attribute = Attribute::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $other->id, 'section' => 'details', 'key' => 'gemeinsam', 'label' => 'gemeinsam',
            'data_type' => Attribute::DATA_TYPE_TEXT, 'sort' => 1,
        ]);

        $this->purge('Testkunde')->assertRedirect();

        $this->assertTrue(Schema::hasColumn('projects', 'attributes_gemeinsam'));
        $this->assertDatabaseHas('attributes', ['id' => $attribute->id]);
    }

    public function test_wrong_name_deletes_nothing(): void
    {
        $this->fill($this->test, 'bleibt');

        $this->purge('testkunde x')->assertSessionHas('error');

        $this->assertDatabaseHas('tenants', ['id' => $this->test->id]);
        $this->assertSame(1, Project::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->test->id)->count());
    }

    public function test_home_organization_cannot_be_purged(): void
    {
        $this->delete(route('admin.kunden.purge', $this->home), ['confirm_name' => $this->home->name])->assertStatus(422);

        $this->assertDatabaseHas('tenants', ['id' => $this->home->id]);
    }

    public function test_central_admins_may_not_purge(): void
    {
        $this->actingAs(User::factory()->create(['tenant_id' => $this->home->id, 'role' => 'central_admin']));

        $this->purge('Testkunde')->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $this->test->id]);
    }

    public function test_a_deleted_active_organization_falls_back_to_the_home_organization(): void
    {
        $this->withSession(['active_tenant_id' => $this->test->id]);
        $this->assertSame($this->test->id, \App\Support\CurrentTenant::id());

        $this->purge('Testkunde');

        $this->assertSame($this->home->id, \App\Support\CurrentTenant::id());
        $this->get(route('dashboard'))->assertOk();
    }
}
