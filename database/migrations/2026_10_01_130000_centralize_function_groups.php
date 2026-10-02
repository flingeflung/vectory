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

    /**
     * Hängt alle Referenzen einer Kunden-Funktionsgruppe auf die zentrale um.
     * Zeilen werden per UPDATE umgehängt (IDs bleiben, damit abhängige Daten
     * wie task_visibilities nicht per Löschkaskade verloren gehen). Nur bei
     * einer Kollision mit bereits vorhandenen Zielzeilen wird zusammengeführt:
     * Planstunden werden addiert, Hauptzuständigkeit und Aufgaben-Sichtbarkeiten
     * bleiben erhalten; reine Zuordnungstabellen haben nichts weiter zu retten.
     */
    private function moveReferences(int $oldId, int $newId): void
    {
        $identities = [
            'function_group_member' => ['person_id'],
            'workflow_step_function_group' => ['workflow_step_id'],
            'project_people' => ['project_id', 'person_id'],
            'project_workflow_step_people' => ['project_workflow_step_id', 'person_id'],
            'project_template_function_group' => ['project_template_id'],
            'project_function_group_hours' => ['project_id'],
            'tasks' => ['project_workflow_step_id', 'person_id'],
        ];

        foreach ($identities as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->where('function_group_id', $oldId)->orderBy('id')->get() as $row) {
                $existing = null;

                // Aufgaben ohne Workflow-Schritt (z. B. aus Illustrationsaufträgen) sind
                // über eigene eindeutige Schlüssel abgesichert und kollidieren hier nie.
                $hasNullIdentity = collect($columns)->contains(fn ($column) => $row->{$column} === null);
                if (! $hasNullIdentity) {
                    $existing = DB::table($table)
                        ->where('function_group_id', $newId)
                        ->where('id', '!=', $row->id)
                        ->where(collect($columns)->mapWithKeys(fn ($column) => [$column => $row->{$column}])->all())
                        ->first();
                }

                if (! $existing) {
                    DB::table($table)->where('id', $row->id)->update(['function_group_id' => $newId]);

                    continue;
                }

                $this->mergeDuplicate($table, $row, $existing);
                DB::table($table)->where('id', $row->id)->delete();
            }
        }
    }

    private function mergeDuplicate(string $table, object $dropped, object $kept): void
    {
        if ($table === 'project_people') {
            DB::table($table)->where('id', $kept->id)->update([
                'is_primary' => (int) $kept->is_primary || (int) $dropped->is_primary,
                'planned_hours' => $kept->planned_hours === null && $dropped->planned_hours === null
                    ? null
                    : (float) $kept->planned_hours + (float) $dropped->planned_hours,
            ]);
        }

        if (in_array($table, ['project_function_group_hours', 'project_template_function_group'], true)) {
            DB::table($table)->where('id', $kept->id)->update([
                'planned_hours' => (float) $kept->planned_hours + (float) $dropped->planned_hours,
            ]);
        }

        if ($table === 'tasks') {
            foreach (DB::table('task_visibilities')->where('task_id', $dropped->id)->get() as $visibility) {
                DB::table('task_visibilities')->insertOrIgnore([
                    'tenant_id' => $visibility->tenant_id,
                    'user_id' => $visibility->user_id,
                    'task_id' => $kept->id,
                    'created_at' => $visibility->created_at,
                    'updated_at' => $visibility->updated_at,
                ]);
            }
        }
    }

    /**
     * Nicht umkehrbar: Die Zusammenführung der Kunden-Funktionsgruppen in die
     * zentralen lässt sich nicht rückgängig machen (die ursprüngliche
     * Zuordnung ist nach dem Umhängen nicht mehr gespeichert). Nicht auf
     * Daten mit echten, kundeneigenen Funktionsgruppen ohne vorherige Sicherung
     * ausführen.
     */
    public function down(): void
    {
        Schema::dropIfExists('function_group_tenant');
    }
};
