<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "3-Ebenen-Hilfestruktur" (Ralf, 2026-09-15): Hilfeseiten lassen sich
     * per Drag & Drop sortieren und ein-/ausrücken (max. 3 Ebenen, in der
     * Verwaltung durchgesetzt). parent_id null = Ebene 1. Beim Löschen
     * eines Eintrags rücken seine Kinder eine Ebene auf (nullOnDelete)
     * statt mitgelöscht zu werden - Inhalte gehen beim Aufräumen der
     * Struktur nicht versehentlich verloren.
     */
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('key')->constrained('help_articles')->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('parent_id');
        });

        // Bestehende Artikel landen sonst alle auf position=0 (Kollision
        // statt Reihenfolge) - einmalig anhand der bisherigen alphabetischen
        // Anzeige durchnummerieren, damit Einrücken/Sortieren ab dem ersten
        // Aufruf funktioniert.
        DB::table('help_articles')->orderBy('key')->get(['id'])->each(function ($article, $index) {
            DB::table('help_articles')->where('id', $article->id)->update(['position' => $index]);
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('position');
        });
    }
};
