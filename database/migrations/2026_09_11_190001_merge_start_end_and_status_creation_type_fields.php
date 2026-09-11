<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Warum nicht 'Start/Ende' und 'Projektstatus/
     * Erstellungsstatus' ... als je eine Zeile" - statt Start+Ende sowie
     * Status+Erstellungsstatus über eine fragile Positions-/Sortier-Kopplung
     * zusammenzuhalten (erst Zufalls-Parität, dann ein explizites
     * Paarungs-Flag - beides ist an Ralfs eigenem Umsortieren gescheitert),
     * rendert je ein gemeinsames System-Feld beide Werte in einer Zeile
     * (siehe system-fields/start_date.blade.php, .../status.blade.php).
     * 'end_date' und 'creation_type' sind deshalb keine eigenen
     * Attribute-Zeilen mehr - die zugrunde liegenden DB-Spalten
     * projects.end_date/creation_type bleiben unverändert bestehen und
     * werden weiterhin vom (unveränderten) start_date-Feld mitgespeichert.
     */
    private const SYSTEM_ORDER = [
        'title', 'start_date', 'project_type', 'version', 'status', 'markets', 'remarks',
    ];

    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            DB::table('attributes')
                ->where('tenant_id', $tenantId)
                ->where('section', 'stammdaten')
                ->where('system', true)
                ->whereIn('key', ['end_date', 'creation_type'])
                ->delete();

            DB::table('attributes')
                ->where('tenant_id', $tenantId)
                ->where('section', 'stammdaten')
                ->where('key', 'start_date')
                ->update(['label' => 'Start/Ende']);

            DB::table('attributes')
                ->where('tenant_id', $tenantId)
                ->where('section', 'stammdaten')
                ->where('key', 'status')
                ->update(['label' => 'Status/Erstellungsstatus']);

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
        // Gelöschte Attribute-Zeilen (end_date/creation_type) und Labels
        // lassen sich nicht sinnvoll zurückführen - wie bei den anderen
        // reinen Struktur-/Sortiermigrationen zuvor bewusst leer.
    }
};
