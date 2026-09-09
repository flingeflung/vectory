<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terminberechnung (Vietto-Vorbild: ajax_workflow_edittermine.php).
     * Alle vier Felder sind bewusst NULLABLE mit "null = wie Vorlage" statt
     * beim Zuweisen einmalig aus workflow_steps kopiert zu werden (anders
     * als Vietto, das bei Workflow-Zuweisung fest kopiert) - konsistent mit
     * der bereits getroffenen Entscheidung "Verweis statt Kopie" (siehe
     * Workflow::isPublished()-Diskussion): solange niemand am Projekt
     * abweicht, zieht sich der Wert weiter live von der Vorlage nach.
     * is_start/is_end sitzen bewusst HIER (pro Projekt), nicht nur an der
     * Vorlage - Ralf: "das muss auf jeden Fall am Projekt sitzen, das ist
     * sehr individuell" (welcher Schritt Start-/Enddatum liefert, kann von
     * Projekt zu Projekt abweichen, anders als z.B. bei Vietto WFS-Feldern,
     * die eher Vorlagen-Charakter haben).
     */
    public function up(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table) {
            $table->string('milestone_title')->nullable()->after('due_date');
            $table->integer('duration_days')->nullable()->after('milestone_title');
            $table->boolean('is_start')->nullable()->after('duration_days');
            $table->boolean('is_end')->nullable()->after('is_start');
        });
    }

    public function down(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table) {
            $table->dropColumn(['milestone_title', 'duration_days', 'is_start', 'is_end']);
        });
    }
};
