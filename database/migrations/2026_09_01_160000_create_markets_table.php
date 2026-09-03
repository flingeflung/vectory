<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Markt = Land+Sprache-Kombination, mandantenscoped. Entspricht Viettos
     * subsprachen (inkl. der dort per Join aufgelösten Land-/Sprachnamen).
     * Land und Sprache existieren in Vietto zwar auch als eigene Tabellen
     * (laender/sprachen), werden aber nirgends unabhängig von der
     * Markt-Kombination gefiltert/angezeigt – daher hier bewusst
     * denormalisiert statt drei Tabellen für ein Konzept.
     */
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('country_iso', 3);
            $table->string('country_name');
            $table->string('language_code', 3);
            $table->string('language_name');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
