<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Einsatzplan" eines Workflows (Ralf, 2026-10-04): Je Funktionsgruppe, von welchem bis zu welchem Schritt
     * sie gebraucht wird. Ohne Eintrag gilt die ganze Breite (alle Arbeitsschritte). Die Ressourcenplanung
     * nutzt das später, um geplante Stunden nicht gleichmäßig über das ganze Projekt, sondern über den
     * tatsächlichen Einsatzzeitraum zu verteilen. Fehlt ein Schritt (gelöscht), gilt die offene Seite
     * (null = erster bzw. letzter Schritt).
     */
    public function up(): void
    {
        Schema::create('workflow_group_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->foreignId('to_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workflow_id', 'function_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_group_windows');
    }
};
