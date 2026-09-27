<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planstunden vs. Ist im "Zeiten"-Reiter (Ralf, 2026-09-27, siehe Roadmap-
 * Backlog): solange NULL, gilt live der Wert aus der verknüpften
 * Aufwandsschablone (Summe project_template_function_group.planned_hours).
 * Ein Projektleiter kann die "Vererbung aufbrechen" - ab dann steht hier ein
 * eigener, frei änderbarer Wert, der Vorrang vor der Schablone hat. Einweg:
 * keine Rückkehr zur Schablonen-Verknüpfung vorgesehen. Ändert NIE die
 * Schablone selbst - project_template_id bleibt unangetastet (die Schablone
 * hat noch andere Zwecke, z.B. Dauer-Schätzung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('planned_hours_override', 6, 2)->nullable()->after('project_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('planned_hours_override');
        });
    }
};
