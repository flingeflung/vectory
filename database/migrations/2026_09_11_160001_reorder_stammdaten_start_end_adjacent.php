<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Start und Ende müssen in einer Zeile nebeneinander
     * stehen" - Start/Ende direkt hinter Bezeichnung einsortiert (siehe
     * Attribute::SYSTEM_FIELDS), damit sie im 2-spaltigen Stammdaten-Raster
     * garantiert dieselbe Zeile teilen. Feste Felder bekommen die neue
     * Reihenfolge fest zugewiesen, alle Zusatzfelder behalten ihre
     * BISHERIGE relative Reihenfolge zueinander - nur neu durchnummeriert,
     * damit nichts kollidiert.
     */
    private const SYSTEM_ORDER = [
        'title', 'start_date', 'end_date', 'project_type', 'version', 'status', 'creation_type', 'markets', 'remarks',
    ];

    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $attributes = DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'stammdaten')->orderBy('sort')->get(['id', 'key', 'system']);

            $systemRows = collect(self::SYSTEM_ORDER)
                ->map(fn ($key) => $attributes->firstWhere('key', $key))
                ->filter();
            $customRows = $attributes->where('system', false)->values();

            $sort = 0;
            foreach ($systemRows->concat($customRows) as $row) {
                DB::table('attributes')->where('id', $row->id)->update(['sort' => $sort]);
                $sort++;
            }
        }
    }

    public function down(): void
    {
        // Reine Sortierreihenfolge, keine sinnvolle Rückrichtung - bleibt
        // absichtlich leer (wie bei anderen reinen Umsortierungen zuvor).
    }
};
