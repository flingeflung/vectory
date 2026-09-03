<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wer für welchen WFS in welcher Funktionsgruppe zuständig ist (kann von
     * der Projekt-weiten Personen-Zuweisung abweichen - manuell pro Schritt
     * überschreibbar, siehe Konzept-Diskussion). Entspricht Viettos
     * workflow_pers_cx2. Nur die Lese-Seite (reine Darstellung) - Zuweisungs-
     * UI/Auto-Sync folgt später.
     */
    public function up(): void
    {
        Schema::create('project_workflow_step_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_workflow_step_id', 'function_group_id', 'person_id'], 'pws_people_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workflow_step_people');
    }
};
