<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Illustration/Grafikerstellung"-Funktionsgruppe war
     * bisher an drei Stellen (GraphicOrderController, IllustrationOverview-
     * Controller, Task::syncWorkflowTasksForProject) hart über
     * `legacy_id = 5` gesucht - funktioniert nur für aus Vietto importierte
     * Mandanten, bei denen die Gruppe zufällig genau diese Vietto-ID hatte.
     * Bei komplett neu in Vectory angelegten Mandanten (z.B. _Standardkunde)
     * gibt es gar keine legacy_id - die Suche lief ins Leere, obwohl eine
     * "Illustration"-Gruppe längst existierte. Jetzt ein echtes, admin-
     * konfigurierbares Flag statt der Zufalls-ID.
     */
    public function up(): void
    {
        Schema::table('function_groups', function (Blueprint $table) {
            $table->boolean('is_illustration_group')->default(false)->after('active');
        });

        // Bestehendes Verhalten für aus Vietto importierte Mandanten 1:1
        // fortführen: die Gruppe mit legacy_id=5 (dort exakt "Grafikerstellung")
        // bekommt das neue Flag automatisch gesetzt.
        DB::table('function_groups')->where('legacy_id', 5)->update(['is_illustration_group' => true]);
    }

    public function down(): void
    {
        Schema::table('function_groups', function (Blueprint $table) {
            $table->dropColumn('is_illustration_group');
        });
    }
};
