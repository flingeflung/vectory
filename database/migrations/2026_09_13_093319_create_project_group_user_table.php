<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gruppe<->Nutzer, bestimmt Sichtbarkeit ("Meine Projektgruppen") -
     * Vietto-Vorbild: gruppen_pers_cx. Kein Besitzer-Konzept, rein diese
     * Tabelle entscheidet, wer eine Gruppe sieht/bearbeitet - echtes
     * Teilen (Ralf, 2026-09-13: "ja, teilbar ist wichtig"), keine Kopie
     * pro Person.
     */
    public function up(): void
    {
        Schema::create('project_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_group_user');
    }
};
