<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projekteigene Schritt-Instanz, entspricht Viettos workflowprogress2.
     * Entsteht beim Zuweisen eines Workflows (alle Schritte der Vorlage
     * werden einmalig hierher kopiert, referenzieren aber weiterhin
     * workflow_steps für Titel/Beschreibung/Funktionsgruppe - siehe
     * Analyse-Notiz im Workflow-Model). Nur die Felder, die sich pro Projekt
     * tatsächlich ändern, sind hier eigene Spalten.
     */
    public function up(): void
    {
        Schema::create('project_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->boolean('is_current')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('milestone_done_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'workflow_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workflow_steps');
    }
};
