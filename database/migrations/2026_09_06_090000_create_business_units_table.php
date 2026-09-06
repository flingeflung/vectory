<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Geschäftsbereich (z.B. bei Viega aktuell "Global"/"Regional") - reines
     * Hilfsattribut einer Person neben Firma/Abteilung, ohne Abhängigkeit zu
     * diesen (bewusst KEINE Zuordnung zu Departments - Ralf: "hat eh keine
     * Auswirkungen auf Prozesse, dient nur als Zuordnungshilfe"). Kundeneigene
     * Liste, kein fester globaler Katalog.
     */
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
