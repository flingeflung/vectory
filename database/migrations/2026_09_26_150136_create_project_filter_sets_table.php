<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ralf, 2026-09-26: eigenständige, benannte Filterset-Sammlung für den
 * Projektfilter - bewusst NICHT dieselben Sets wie beim Anzeigefilter
 * (DisplayFilterSet), obwohl deren "config" bisher schon nebenbei
 * filter_fields/filter_values mitschleppte (siehe ProjectFilterCatalog vor
 * diesem Umbau). Ralfs Entscheidung: unabhängig voneinander frei
 * kombinierbar (z. B. immer dieselben Spalten, aber wechselnde
 * Projektfilter-Sets wie "Meine kritischen Projekte", "Kunde X offen").
 * Gleiche Struktur wie display_filter_sets, nur eigene Tabelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_filter_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_filter_sets');
    }
};
