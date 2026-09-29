<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-29: Abteilungsfilter in der Planung funktioniert nicht
     * zuverlässig (passt nicht für alle Szenarien) - ersetzt durch eine
     * direkte, manuell gepflegte Markierung je Person ("Diese Person in die
     * Ressourcenplanung mit einbeziehen"). Default true, damit sich am
     * bisherigen Verhalten (alle Login-Personen sichtbar) erstmal nichts
     * ändert - einzelne Personen (z.B. externe Dienstleister wie Florian)
     * werden dann gezielt ausgeschlossen, statt dass alle erst mühsam wieder
     * einzeln eingeschlossen werden müssten.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('resource_planning')->default(true)->after('is_absent');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('resource_planning');
        });
    }
};
