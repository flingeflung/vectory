<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prozentuale Aufteilung (Ralf, 2026-09-27, siehe Roadmap-Backlog): pro
 * Hauptprojekt eine Zeile je Empfänger (Hauptprojekt selbst + jedes
 * Unterprojekt), Summe muss beim Speichern 100 ergeben (App-Ebene, nicht
 * DB-Constraint). Nur der aktuell gültige Stand wird gespeichert - keine
 * Historie, weil eine Buchung die damals gültigen Prozente sofort als
 * echte job_hours-Zeilen "einfriert" (siehe ProjectHourController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_percentage_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hauptprojekt_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['hauptprojekt_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_percentage_splits');
    }
};
