<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-18: Schrittweite bei der Dauer auf halbe Wochen -
     * duration_value muss dafür Nachkommastellen können (bisher
     * unsignedSmallInteger).
     */
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->decimal('duration_value', 5, 1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_value')->change();
        });
    }
};
