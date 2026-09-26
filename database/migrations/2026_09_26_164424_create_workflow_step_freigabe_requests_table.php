<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hält die Auswahl fest, die der TR beim Auslösen eines Freigabe-WFS trifft
 * (Quell-PDF im gesperrten Projektverzeichnis, Zielordner im lokalen
 * Arbeitsverzeichnis für beide möglichen Ausgänge), bis der externe
 * Empfänger Wochen später auf den Mail-Link klickt - siehe
 * ProjectWorkflowStepController::activate() (Auslöser) und
 * WorkflowStepFreigabeActionController (Freigabe-/Korrektur-Klick, ganz
 * ohne Login, nur über signierten Link).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('source_pdf_path', 1000);
            $table->string('freigabe_target_path', 1000)->nullable();
            $table->string('korrektur_target_path', 1000);
            // pending -> freigegeben | korrektur_hochgeladen (Endzustände, kein Zurück)
            $table->string('status', 30)->default('pending');
            $table->text('korrektur_kommentar')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_freigabe_requests');
    }
};
