<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('function_group_tenant')) {
            Schema::create('function_group_tenant', function (Blueprint $table) {
                $table->id();
                $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['function_group_id', 'tenant_id']);
            });
        }

        if (DB::table('system_settings')->where('key', 'multi_tenant_enabled')->value('value') !== '1') {
            return;
        }

        $homeTenantId = DB::table('tenants')->where('is_home_tenant', true)->value('id');
        if (! $homeTenantId) {
            return;
        }

        DB::transaction(function () use ($homeTenantId) {
            $homeGroups = DB::table('function_groups')->where('tenant_id', $homeTenantId)->get();
            $customerGroups = DB::table('function_groups')->where('tenant_id', '!=', $homeTenantId)->orderBy('id')->get();

            foreach ($customerGroups as $group) {
                $canonical = $homeGroups->first(fn ($candidate) => mb_strtolower(trim($candidate->short_name)) === mb_strtolower(trim($group->short_name)))
                    ?? $homeGroups->first(fn ($candidate) => mb_strtolower(trim($candidate->name)) === mb_strtolower(trim($group->name)))
                    ?? ($group->is_illustration_group
                        ? $homeGroups->first(fn ($candidate) => (bool) $candidate->is_illustration_group)
                        : null);

                if (! $canonical) {
                    $canonicalId = DB::table('function_groups')->insertGetId([
                        'tenant_id' => $homeTenantId,
                        'legacy_id' => null,
                        'name' => $group->name,
                        'short_name' => $group->short_name,
                        'sort' => $group->sort,
                        'active' => $group->active,
                        'is_illustration_group' => $group->is_illustration_group,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $canonical = DB::table('function_groups')->where('id', $canonicalId)->first();
                    $homeGroups->push($canonical);
                }

                DB::table('function_group_tenant')->insertOrIgnore([
                    'function_group_id' => $canonical->id,
                    'tenant_id' => $group->tenant_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->moveReferences((int) $group->id, (int) $canonical->id);
                DB::table('function_groups')->where('id', $group->id)->delete();
            }
        });
    }

    private function moveReferences(int $oldId, int $newId): void
    {
        $definitions = [
            'function_group_member' => ['function_group_id', 'person_id'],
            'workflow_step_function_group' => ['workflow_step_id', 'function_group_id'],
            'project_people' => ['project_id', 'function_group_id', 'person_id'],
            'project_workflow_step_people' => ['project_workflow_step_id', 'function_group_id', 'person_id'],
            'project_template_function_group' => ['project_template_id', 'function_group_id'],
            'project_function_group_hours' => ['project_id', 'function_group_id'],
            'tasks' => ['project_workflow_step_id', 'function_group_id', 'person_id'],
        ];

        foreach ($definitions as $table => $uniqueColumns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->where('function_group_id', $oldId)->get() as $row) {
                $data = (array) $row;
                unset($data['id']);
                $data['function_group_id'] = $newId;
                $identityColumns = $table === 'tasks' && $data['graphic_order_id'] !== null
                    ? ['graphic_order_id', 'person_id']
                    : $uniqueColumns;
                $identity = collect($identityColumns)->mapWithKeys(fn ($column) => [$column => $data[$column]])->all();

                if (! DB::table($table)->where($identity)->exists()) {
                    DB::table($table)->insert($data);
                } elseif ($table === 'project_people') {
                    DB::table($table)->where($identity)->update([
                        'is_primary' => DB::raw('GREATEST(is_primary, '.((int) $row->is_primary).')'),
                        'planned_hours' => DB::raw('COALESCE(planned_hours, '.($row->planned_hours === null ? 'NULL' : (float) $row->planned_hours).')'),
                    ]);
                }
            }

            DB::table($table)->where('function_group_id', $oldId)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('function_group_tenant');
    }
};
