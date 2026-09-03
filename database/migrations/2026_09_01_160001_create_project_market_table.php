<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projekt ↔ Markt (m:n), entspricht Viettos laender_pn_cx. Nur die
     * Zuordnung selbst – Lokalisierungsstatus/Priorität/Publikationsdatum
     * folgen erst, falls dafür mal ein eigener Filter/eine eigene Anzeige
     * gebraucht wird.
     */
    public function up(): void
    {
        Schema::create('project_market', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'market_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_market');
    }
};
