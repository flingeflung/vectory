<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Print-Formate-Feature, Schritt 4 (Vietto: projekte.valFormatKombiID /
 * strSizeBogen / strSizeFertigesDok). Formattyp "Standard-Formate" nutzt
 * paper_format_combination_id, Formattyp "Variable Formate" die beiden
 * Freitext-Spalten - siehe ProjectTypeSub::formatTypes(). Anders als Vietto
 * keine zusätzlichen valSizeBogenID/valSizeFertigesDokID-Spalten nötig, da
 * "Variable Formate" bewusst reiner Freitext ohne Katalog-Bezug ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('paper_format_combination_id')->nullable()->after('attributes')->constrained('paper_format_combinations')->restrictOnDelete();
            $table->string('input_format_free_text')->nullable()->after('paper_format_combination_id');
            $table->string('output_format_free_text')->nullable()->after('input_format_free_text');
        });

        DB::table('attributes')->where('key', 'format')->update(['system' => true]);
    }

    public function down(): void
    {
        DB::table('attributes')->where('key', 'format')->update(['system' => false]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paper_format_combination_id');
            $table->dropColumn(['input_format_free_text', 'output_format_free_text']);
        });
    }
};
