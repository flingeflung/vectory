<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Die vorherige Migration hat nur den langen Namen umbenannt - die
     * Kurzform (u.a. bei "Projektbeteiligte Personen" sichtbar) hieß noch
     * "Grafik". Der Import-Befehl überschreibt das ab sofort nicht mehr
     * (siehe ImportFunctionGroupsFromVietto).
     */
    public function up(): void
    {
        DB::table('function_groups')->where('legacy_id', 5)->update(['short_name' => 'Illu']);
    }

    public function down(): void
    {
        DB::table('function_groups')->where('legacy_id', 5)->update(['short_name' => 'Grafik']);
    }
};
