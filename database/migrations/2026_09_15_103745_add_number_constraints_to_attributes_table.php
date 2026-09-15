<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-15: Zahl-Attribute bekommen optional eine Mindest-/
     * Höchstzahl sowie eine feste Anzahl Dezimalstellen. Alle drei bleiben
     * nullable/ungesetzt (= keine Einschränkung) - anders als data_type/
     * multiple sind das reine Validierungsgrenzen, keine
     * Datenablage-Entscheidung, deshalb (anders als dort) auch nachträglich
     * änderbar, siehe Attribute-Model-Docblock.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->decimal('number_min', 15, 4)->nullable()->after('multiple');
            $table->decimal('number_max', 15, 4)->nullable()->after('number_min');
            $table->unsignedTinyInteger('number_decimals')->nullable()->after('number_max');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['number_min', 'number_max', 'number_decimals']);
        });
    }
};
