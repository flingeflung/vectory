<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-18: einem Projekt eine Projektschablone zuordnen können
     * - neues System-Feld in Ablaufdaten (Ralf: "das ist aus meiner Sicht
     * mehr 'Ablauf'"), analog den bisherigen Nachzüglern (siehe
     * 2026_09_11_100001_add_readonly_ablaufdaten_system_fields.php). Wird
     * ans Ende von Ablaufdaten angehängt, Ralf kann die Position wie jedes
     * andere System-Feld per Drag&Drop selbst ändern.
     */
    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            if (DB::table('attributes')->where('tenant_id', $tenantId)->where('key', 'project_template')->exists()) {
                continue;
            }

            $sort = 1 + (int) DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'ablaufdaten')->max('sort');

            DB::table('attributes')->insert([
                'tenant_id' => $tenantId,
                'section' => 'ablaufdaten',
                'system' => true,
                'key' => 'project_template',
                'label' => 'Projektschablone',
                'data_type' => 'text',
                'sort' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('attributes')->where('key', 'project_template')->where('system', true)->delete();
    }
};
