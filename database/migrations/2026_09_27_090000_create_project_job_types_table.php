<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erster Baustein der Job-Zeiterfassung/Projekt-Verknüpfung (Ralf,
 * 2026-09-27, siehe Roadmap-Backlog "Zeiterfassung/Ressourcenplanung"):
 * welche Jobs (job_types) für ein Projekt überhaupt relevant sind - reine
 * Zuordnung, noch KEINE Stundenbuchung. Analog zu person_job_types
 * (JobloadController::saveJobs), nur je Projekt statt je Person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_job_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'job_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_job_types');
    }
};
