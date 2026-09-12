<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-12: "Änderungen zur Vorversion" (Vietto-Wortlaut/ÄzV)
     * hatte er wiederholt nach einem besseren Namen gefragt - heißt jetzt
     * "Änderungsprotokoll". Die migrierten Zeilen aus
     * 2026_09_11_100001_add_readonly_ablaufdaten_system_fields.php tragen
     * noch den alten key/label, deshalb hier per Update statt die alte
     * Migration nachträglich zu ändern (die ist schon gelaufen).
     */
    public function up(): void
    {
        DB::table('attributes')
            ->where('key', 'changes_vs_previous_version')
            ->update(['key' => 'change_log', 'label' => 'Änderungsprotokoll']);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('key', 'change_log')
            ->update(['key' => 'changes_vs_previous_version', 'label' => 'Änderungen zur Vorversion']);
    }
};
