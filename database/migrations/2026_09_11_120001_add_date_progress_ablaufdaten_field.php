<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Datumsfortschritt" (zeitbasierter Balken Start-/
     * Enddatum vs. heute, Vietto-Vorbild "Zeitbalken") direkt oberhalb von
     * "Projektfortschritt" einsortiert - siehe Attribute::SYSTEM_FIELDS.
     */
    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            if (DB::table('attributes')->where('tenant_id', $tenantId)->where('key', 'date_progress')->exists()) {
                continue;
            }

            $progressSort = DB::table('attributes')->where('tenant_id', $tenantId)->where('key', 'progress')->value('sort');

            if ($progressSort !== null) {
                // Direkt vor "progress" einreihen: dessen Zeile und alles
                // danach um 1 nach hinten schieben, dann davor einfügen.
                DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'ablaufdaten')->where('sort', '>=', $progressSort)->increment('sort');
                $sort = $progressSort;
            } else {
                $sort = 1 + (int) DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'ablaufdaten')->max('sort');
            }

            DB::table('attributes')->insert([
                'tenant_id' => $tenantId,
                'section' => 'ablaufdaten',
                'system' => true,
                'key' => 'date_progress',
                'label' => 'Datumsfortschritt',
                'data_type' => 'text',
                'sort' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('attributes')->where('key', 'date_progress')->where('system', true)->delete();
    }
};
