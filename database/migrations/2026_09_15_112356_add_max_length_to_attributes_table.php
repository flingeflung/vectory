<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-15: Obergrenze für die Textlänge, konfigurierbar für
     * Text (einzeilig) UND Textarea (mehrzeilig) - EINE gemeinsame Spalte,
     * genau wie number_min/max/decimals nur bei ihrem jeweiligen Feldtyp
     * ausgewertet wird (siehe Attribute-Model). Bleibt sie leer, ändert
     * sich am bisherigen Verhalten nichts (Text weiterhin max. 255 Zeichen
     * als Vorgabe, Textarea weiterhin unbegrenzt) - reine Opt-in-Ergänzung,
     * kein Verhaltenswechsel für bestehende Attribute.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->unsignedInteger('max_length')->nullable()->after('number_decimals');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('max_length');
        });
    }
};
