<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Ist es nun so, dass Start+Ende sowie die beiden
     * Status-Felder immer Paare bilden?" - Antwort: nein, das war reiner
     * Zufall der Reihenfolge (gerade/ungerade Anzahl schmaler Felder
     * davor). Echte, robuste Kopplung jetzt über dieses Flag statt
     * Zufalls-Parität - siehe detail.blade.php Zeilen-Algorithmus.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('same_row_as_next')->default(false)->after('sort');
        });

        // Die zwei schon gewünschten Paare gleich mit anlegen.
        DB::table('attributes')->whereIn('key', ['start_date', 'status'])->where('system', true)->update(['same_row_as_next' => true]);
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('same_row_as_next');
        });
    }
};
