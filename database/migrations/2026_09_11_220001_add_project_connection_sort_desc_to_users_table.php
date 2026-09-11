<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persönliche Einstellung: Sortierrichtung der Projektliste im
     * "Verknüpfen"-Dialog (Ralf, 2026-09-11: hohe PNs sollen bei Bedarf
     * auch oben stehen können) - wird sich gemerkt, damit man sie nicht
     * jedes Mal neu umstellen muss.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('project_connection_sort_desc')->default(false)->after('hide_discarded_projects_on_reset');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('project_connection_sort_desc');
        });
    }
};
