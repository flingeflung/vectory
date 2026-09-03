<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Meine Filtersets" (Anzeigefilter) je Benutzer: welche Spalten sichtbar
     * sind, in welcher Reihenfolge, und ob Langtexte aufgeklappt sind.
     * Analogon zu Viettos filter-Tabelle (strBereich='anzeigefilter_pueb').
     */
    public function up(): void
    {
        Schema::create('display_filter_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('config');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_filter_sets');
    }
};
