<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Der Erstellungsstatus ist auch ein Stammdatum
     * eines Projekts" - verschiebt die schon angelegten system=true-
     * Attribute-Zeilen von Ablaufdaten nach Stammdaten (ans Ende
     * einsortiert, frei per Drag&Drop umsortierbar).
     */
    public function up(): void
    {
        $rows = DB::table('attributes')->where('key', 'creation_type')->where('system', true)->get(['id', 'tenant_id']);

        foreach ($rows as $row) {
            $nextSort = 1 + (int) DB::table('attributes')->where('tenant_id', $row->tenant_id)->where('section', 'stammdaten')->max('sort');

            DB::table('attributes')->where('id', $row->id)->update([
                'section' => 'stammdaten',
                'sort' => $nextSort,
            ]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('attributes')->where('key', 'creation_type')->where('system', true)->get(['id', 'tenant_id']);

        foreach ($rows as $row) {
            $nextSort = 1 + (int) DB::table('attributes')->where('tenant_id', $row->tenant_id)->where('section', 'ablaufdaten')->max('sort');

            DB::table('attributes')->where('id', $row->id)->update([
                'section' => 'ablaufdaten',
                'sort' => $nextSort,
            ]);
        }
    }
};
