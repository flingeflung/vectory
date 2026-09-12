<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * project_type_main/project_type_sub waren die rohen Vietto-Legacy-
     * Codes, nur einmalig genutzt, um project_type_main_id/
     * project_type_sub_id zu befüllen (siehe ehemaliger Befehl
     * BackfillProjectTypeIds, inzwischen entfernt). Nirgends mehr
     * gelesen - die echten FKs sind seitdem die alleinige Quelle.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_type_main', 'project_type_sub']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->smallInteger('project_type_main')->nullable();
            $table->smallInteger('project_type_sub')->nullable();
        });
    }
};
