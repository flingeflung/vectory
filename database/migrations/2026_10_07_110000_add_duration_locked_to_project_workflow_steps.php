<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gesperrte Dauer am Projekt-Schritt (Ralf, 2026-10-07): weicht die Sperre im Projekt von der Voreinstellung am Workflow-Schritt ab,
 * steht der Wert hier. null = Voreinstellung des Workflow-Schritts gilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table) {
            $table->boolean('duration_locked')->nullable()->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table) {
            $table->dropColumn('duration_locked');
        });
    }
};
