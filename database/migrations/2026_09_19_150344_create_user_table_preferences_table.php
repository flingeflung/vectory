<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-19: jeder Benutzer stellt sich die Spaltenbreiten der großen
     * Tabellenansichten selbst ein. Bewusst pro Benutzer + Tabelle (nicht pro
     * Kunde oder Anzeigefilter-Set): eine kleine JSON-Zeile, unabhängig davon,
     * bei welchem Kunden man gerade arbeitet.
     */
    public function up(): void
    {
        Schema::create('user_table_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('table_key', 64);
            $table->json('column_widths');
            $table->timestamps();

            $table->unique(['user_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_table_preferences');
    }
};
