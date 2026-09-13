<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gruppe<->Projekt (Vietto-Vorbild: gruppen_cx). Echte FK mit
     * cascadeOnDelete auf project_id statt Viettos strPN-Freitext-Verweis -
     * behebt einen konkreten Vietto-Bug (ajax_delprojekt.php räumt
     * gruppen_cx beim Projekt-Löschen nicht auf, verwaiste Zeilen bleiben
     * stehen und zählen trotzdem in der Gruppen-Projektanzahl mit).
     */
    public function up(): void
    {
        Schema::create('project_group_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_group_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_group_project');
    }
};
