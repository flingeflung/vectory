<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-19: Wochenstunden je Person (Raster halbe Stunde) plus ein
     * Standardwert je Mandant, der beim Anlegen eines Logins automatisch in die
     * Person übernommen wird. Startwert für alle Mandanten: 39 Stunden.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->decimal('weekly_hours', 4, 1)->nullable()->after('remarks');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('default_weekly_hours', 4, 1)->default(39.0)->after('jobload_time_grid');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('default_weekly_hours');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('weekly_hours');
        });
    }
};
