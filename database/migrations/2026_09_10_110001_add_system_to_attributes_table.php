<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf: die festen Felder (Bezeichnung, Baujahr, ...) sollen sich frei
     * mit den Zusatzfeldern mischen lassen, nicht nur als fixer Block vor
     * ihnen stehen. Dafür bekommen sie jetzt eigene Attribute-Zeilen
     * (system=true) statt nur einer hartkodierten Anzeigeliste - sie
     * tragen damit einen echten, pro Kunde änderbaren sort-Wert wie jedes
     * andere Attribut. system=true sperrt Umbenennen/Löschen über die
     * Verwaltungsseite (siehe AttributeController) - nur die Reihenfolge
     * ist änderbar.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('system')->default(false)->after('section');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('system');
        });
    }
};
