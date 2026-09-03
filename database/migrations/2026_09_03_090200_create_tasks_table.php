<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aufgabenliste einer Person - analog Viettos "aufgaben"-Tabelle. Anders
     * als dort NICHT per Observer/Event automatisch gepflegt, sondern per
     * explizitem Rebuild-Aufruf an definierten Schreibstellen (siehe
     * Task::syncWorkflowTasksForProject()) - passt zum bestehenden Muster
     * im Code (project_people wird beim Speichern z.B. auch komplett neu
     * aufgebaut statt inkrementell gepflegt).
     *
     * "source" unterscheidet automatisch aus dem Workflow abgeleitete
     * Aufgaben (werden bei jedem Rebuild gelöscht/neu angelegt) von künftig
     * manuell zugewiesenen (bleiben beim Rebuild unangetastet) - die
     * Zuweisungs-UI dafür folgt später, die Spalte ist schon jetzt da, damit
     * dafür keine weitere Migration nötig wird.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('function_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_workflow_step_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source')->default('workflow_step');
            $table->timestamps();

            $table->unique(['project_workflow_step_id', 'function_group_id', 'person_id'], 'tasks_unique_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
