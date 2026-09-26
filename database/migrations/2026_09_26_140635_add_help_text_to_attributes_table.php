<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ralf, 2026-09-26: Erklärtext zu einem individuellen Projektattribut, den
 * dessen Ersteller unter "Weitere Optionen" hinterlegen kann. Ist er
 * gesetzt, zeigen die Projektdetails ein kleines Info-Symbol neben dem
 * Feld, das den Text in einem kurzen Hinweis-Overlay anzeigt (siehe
 * AttributeController::moreOptions(), attribute-field.blade.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->text('help_text')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('help_text');
        });
    }
};
