<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: sechs neue, bewusst nicht editierbare Ablaufdaten-
     * Anzeigefelder nach Vietto-Vorbild (siehe Attribute::SYSTEM_FIELDS für
     * die Erklärung, warum das hier dupliziert statt importiert wird -
     * Migrationen bleiben unabhängig vom aktuellen Modellstand).
     */
    private const NEW_FIELDS = [
        'progress' => 'Projektfortschritt',
        'creation_type' => 'Erstellungsstatus',
        'checklist' => 'Checkliste',
        'project_connections' => 'Projektverknüpfungen',
        'remarks_echo' => 'Bemerkungen',
        'changes_vs_previous_version' => 'Änderungen zur Vorversion',
    ];

    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $sort = 1 + (int) DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'ablaufdaten')->max('sort');

            foreach (self::NEW_FIELDS as $key => $label) {
                if (DB::table('attributes')->where('tenant_id', $tenantId)->where('key', $key)->exists()) {
                    continue;
                }

                DB::table('attributes')->insert([
                    'tenant_id' => $tenantId,
                    'section' => 'ablaufdaten',
                    'system' => true,
                    'key' => $key,
                    'label' => $label,
                    'data_type' => 'text',
                    'sort' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sort++;
            }
        }
    }

    public function down(): void
    {
        DB::table('attributes')->whereIn('key', array_keys(self::NEW_FIELDS))->where('system', true)->delete();
    }
};
