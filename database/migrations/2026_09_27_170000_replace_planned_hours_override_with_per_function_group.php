<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planstunden-Korrektur (Ralf, 2026-09-27, siehe Roadmap-Backlog): ein
 * einzelner Gesamt-Wert nach dem "Lösen" von der Aufwandsschablone reicht
 * nicht - "dadurch habe ich keine Möglichkeit mehr, zu erkennen, aus
 * welchen Stundenpaketen es sich rekrutiert." Ersetzt projects.
 * planned_hours_override (aus der vorherigen Migration, noch nicht
 * gepusht) durch eine Pivot-Tabelle analog project_template_function_group
 * - "Lösen" kopiert den aktuellen Schablonen-Stand hierher, ab dann je
 * Funktionsgruppe unabhängig änderbar, gleiches Bearbeitungsmuster wie bei
 * der Schablone selbst (admin/project-templates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('planned_hours_override');
        });

        Schema::create('project_function_group_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_hours', 6, 2);
            $table->timestamps();

            $table->unique(['project_id', 'function_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_function_group_hours');

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('planned_hours_override', 6, 2)->nullable()->after('project_template_id');
        });
    }
};
