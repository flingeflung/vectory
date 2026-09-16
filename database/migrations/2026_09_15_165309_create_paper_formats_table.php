<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Basis-Katalog für Print-Papierformate (Ralf, 2026-09-15, Vietto-
     * Analyse "Print-Formate": entspricht Viettos `formate`-Tabelle, aber
     * ohne deren nie genutztes `intTyp`-Unterscheidungsfeld - laut Ralf
     * "ohne Funktion", Grund nicht mehr rekonstruierbar). Reine Einzelgrößen
     * (z.B. "DIN A6 hoch"); die eigentlich nutzbaren Format-KOMBINATIONEN
     * (Ausgangsformat -> Endformat) sind ein eigener, späterer Schritt
     * (Vietto: `formate_cx`).
     *
     * "active" statt Löschen: einmal verwendete Formate müssen wegen
     * bestehender Projekt-Verknüpfungen (kommt in Schritt 4 dazu) dauerhaft
     * bestehen bleiben, dürfen nur nicht mehr neu wählbar sein - gleiches
     * Prinzip wie überall sonst im Projekt (Workflows, Projektkategorien).
     */
    public function up(): void
    {
        Schema::create('paper_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->unsignedInteger('width_mm');
            $table->unsignedInteger('height_mm');
            $table->string('remark')->nullable();
            $table->boolean('show_dimensions')->default(true);
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paper_formats');
    }
};
