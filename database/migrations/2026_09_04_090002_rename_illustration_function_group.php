<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Viettos Funktionsgruppe "Grafikerstellung" (legacy_id 5) heißt bei uns
     * einheitlich "Illustration" - siehe Absprache mit Ralf, die Illustratoren
     * sollen nirgends mehr "Grafik..." lesen. Der Import-Befehl überschreibt
     * das ab sofort nicht mehr (siehe ImportFunctionGroupsFromVietto).
     */
    public function up(): void
    {
        DB::table('function_groups')->where('legacy_id', 5)->update(['name' => 'Illustration']);
    }

    public function down(): void
    {
        DB::table('function_groups')->where('legacy_id', 5)->update(['name' => 'Grafikerstellung']);
    }
};
