<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Globale, installationsweite Referenzliste - bewusst OHNE tenant_id
     * (anders als fast alles sonst in Vectory). Länder sind ISO-genormt und
     * für jeden Kunden gleich - jeder Kunde wählt nur aus, welche Länder
     * für ihn als Markt eine Rolle spielen (siehe Market-Modell), tippt sie
     * nicht mehr einzeln ab (Ralf: "das ist ja weltweit nach ISO
     * festgelegt... die würde ich hier schon mal komplett aus Vietto
     * übernehmen").
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso', 10)->nullable();
            $table->string('name');
            $table->string('short_name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
