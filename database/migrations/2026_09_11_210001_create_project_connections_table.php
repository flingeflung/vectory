<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Projektverknüpfungen" (Ralf, 2026-09-11) - Vietto-Vorbild:
     * projektverbindungen (pn1, pn2, zwei freie Richtungs-Bezeichnungen
     * strVerbindungLR/RL). Hier als EINE Zeile pro Verknüpfung mit beiden
     * Richtungstexten, statt zwei Zeilen - beim Anzeigen auf Projekt A wird
     * "label" verwendet (A -> B), auf Projekt B "label_reverse" (B -> A),
     * siehe Project::connections().
     */
    public function up(): void
    {
        Schema::create('project_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('related_project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('label');
            $table->string('label_reverse');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'related_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_connections');
    }
};
