<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-18: einem Projekt eine der Projektschablonen (Step 1
     * der Kapa-Planung) zuordnen können - reiner Verweis, keine
     * Übernahme von Werten aus der Schablone ins Projekt.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_template_id')->nullable()->after('project_type_sub_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_template_id');
        });
    }
};
