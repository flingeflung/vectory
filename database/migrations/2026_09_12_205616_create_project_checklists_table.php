<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zuordnung Checkliste <-> Projekt (Vietto: checklist_projekt_cx) -
     * rein manuell (Ralf, 2026-09-12: "die Zuordnung bleibt manuell"), n:m,
     * ein neues Projekt startet ohne aktivierte Checkliste. Echter
     * Unique-Index (in Vietto gab's den nicht, siehe Analyse).
     */
    public function up(): void
    {
        Schema::create('project_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activated_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'checklist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_checklists');
    }
};
