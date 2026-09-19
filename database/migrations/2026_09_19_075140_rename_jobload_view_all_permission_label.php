<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-19: das Recht schaltet jetzt zusätzlich die Auswertung
     * "Nach Jobgruppen" frei und heißt deshalb "alle Personen + Auswertung sehen".
     */
    public function up(): void
    {
        DB::table('permissions')->where('key', 'jobload.overview.view_all')
            ->update(['label' => 'Zeiterfassung: alle Personen + Auswertung eines Kunden sehen', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'jobload.overview.view_all')
            ->update(['label' => 'Zeiterfassung: Übersicht aller Personen eines Kunden sehen', 'updated_at' => now()]);
    }
};
