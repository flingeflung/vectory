<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Globaler Katalog "welche Sprachen sind in welchem Land relevant" -
     * die rechte Tabelle der Märkte-Seite. Aus Viettos subsprachen
     * übernommen (siehe ImportCountryLanguagesFromVietto), global wie
     * countries/languages selbst.
     */
    public function up(): void
    {
        Schema::create('country_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['country_id', 'language_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_languages');
    }
};
