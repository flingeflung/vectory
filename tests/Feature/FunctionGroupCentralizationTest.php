<?php

namespace Tests\Feature;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ->get(route('admin.function-groups'))
            ->assertOk()
            ->assertSee(__('Gültig für Organisation: :home', ['home' => $home->short_name ?? $home->name]));

        $this->post(route('admin.function-groups.availability.update'), [
                'availability' => [$available->id => [$customer->id]],
            ])
            ->assertRedirect(route('admin.function-groups'));

        $this->withSession(['active_tenant_id' => $customer->id])
            ->get(route('admin.function-groups'))
            ->assertOk()
            ->assertSee('Technische Redaktion')
            ->assertDontSee('Technische Illustration')
            ->assertSee(__('Gültig für Organisation :home & :tenant, Konfiguration bei :home im Admin-Bereich', [
                'home' => $home->short_name ?? $home->name,
                'tenant' => $customer->short_name ?? $customer->name,
            ]));

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

    public function test_centralization_migration_keeps_task_visibilities_and_merges_duplicate_hours(): void
    {
        $home = Tenant::query()->firstOrFail();
        $home->update(['is_home_tenant' => true]);
        $customer = Tenant::query()->create(['name' => 'Kunde']);
        SystemSetting::set(SystemSetting::MULTI_TENANT_ENABLED, '1');

        $central = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $home->id, 'name' => 'Technische Redaktion', 'short_name' => 'TR']);
        $dupe = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $customer->id, 'name' => 'Redaktion beim Kunden', 'short_name' => 'tr']);
        $own = FunctionGroup::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $customer->id, 'name' => 'Nur beim Kunden', 'short_name' => 'NK']);

        $project = Project::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $customer->id, 'source_pn' => '269950', 'title' => 'Migrationstest', 'status' => 0]);
        $person = Person::query()->withoutGlobalScope('tenant')->create(['tenant_id' => $customer->id, 'last_name' => 'Kunde', 'active' => true]);
        $user = User::factory()->create(['tenant_id' => $customer->id]);
        $now = now();

        $keptTask = $duplicateTask = $loneTask = null;
        Schema::disableForeignKeyConstraints();
        try {
            foreach ([$central->id => 2.5, $dupe->id => 4.0] as $groupId => $hours) {
                DB::table('project_function_group_hours')->insert(['tenant_id' => $customer->id, 'project_id' => $project->id, 'function_group_id' => $groupId, 'planned_hours' => $hours, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('project_people')->insert(['tenant_id' => $customer->id, 'project_id' => $project->id, 'function_group_id' => $groupId, 'person_id' => $person->id, 'is_primary' => $groupId === $dupe->id, 'planned_hours' => $hours, 'created_at' => $now, 'updated_at' => $now]);
            }
            $taskRow = fn (int $groupId, int $stepId) => ['tenant_id' => $customer->id, 'project_id' => $project->id, 'person_id' => $person->id, 'function_group_id' => $groupId, 'project_workflow_step_id' => $stepId, 'source' => 'workflow_step', 'created_at' => $now, 'updated_at' => $now];
            $keptTask = DB::table('tasks')->insertGetId($taskRow($central->id, 901));
            $duplicateTask = DB::table('tasks')->insertGetId($taskRow($dupe->id, 901));
            $loneTask = DB::table('tasks')->insertGetId($taskRow($own->id, 902));
            DB::table('task_visibilities')->insert([
                ['tenant_id' => $customer->id, 'user_id' => $user->id, 'task_id' => $duplicateTask, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => $customer->id, 'user_id' => $user->id, 'task_id' => $loneTask, 'created_at' => $now, 'updated_at' => $now],
            ]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Die Workflow-Schritt-IDs der Aufgaben sind hier nur Platzhalter.
        Schema::disableForeignKeyConstraints();
        (require database_path('migrations/2026_10_01_130000_centralize_function_groups.php'))->up();
        Schema::enableForeignKeyConstraints();

        $this->assertFalse(DB::table('function_groups')->where('id', $dupe->id)->exists());
        $this->assertSame(1, DB::table('function_group_tenant')->where(['function_group_id' => $central->id, 'tenant_id' => $customer->id])->count());

        $this->assertSame('6.50', (string) DB::table('project_function_group_hours')->where(['project_id' => $project->id, 'function_group_id' => $central->id])->value('planned_hours'));
        $merged = DB::table('project_people')->where(['project_id' => $project->id, 'function_group_id' => $central->id])->first();
        $this->assertSame(6.5, (float) $merged->planned_hours);
        $this->assertSame(1, (int) $merged->is_primary);

        // Dublette: Sichtbarkeit wandert auf die behaltene Aufgabe. Einzelaufgabe: gleiche ID, Sichtbarkeit bleibt.
        $this->assertTrue(DB::table('task_visibilities')->where(['task_id' => $keptTask, 'user_id' => $user->id])->exists());
        $newCentralId = DB::table('function_groups')->where(['tenant_id' => $home->id, 'short_name' => 'NK'])->value('id');
        $this->assertNotNull($newCentralId);
        $this->assertSame((int) $newCentralId, (int) DB::table('tasks')->where('id', $loneTask)->value('function_group_id'));
        $this->assertTrue(DB::table('task_visibilities')->where(['task_id' => $loneTask, 'user_id' => $user->id])->exists());
        $this->assertFalse(DB::table('tasks')->where('id', $duplicateTask)->exists());
    }
}
