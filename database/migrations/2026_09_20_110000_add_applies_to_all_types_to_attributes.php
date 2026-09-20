<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Geltung nach Projektart für ALLE Bereiche (Ralf, 2026-09-20): bisher gab es die
 * Zuordnung zu Projektarten (attribute_project_type) nur im Bereich
 * "Typspezifisch". Jetzt kann jedes Zusatzfeld - und einzelne Systemfelder wie
 * Modelle/Märkte - unabhängig vom Bereich auf ausgewählte Projektarten
 * beschränkt werden. applies_to_all_types = true heißt "gilt für alle" (der
 * bisherige Zustand von Stammdaten/Ablaufdaten), typspezifische Felder bleiben
 * auf false und gelten wie bisher nur, wo sie zugewiesen sind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('applies_to_all_types')->default(true)->after('label_editable');
        });

        DB::table('attributes')->where('section', 'typspezifisch')->update(['applies_to_all_types' => false]);
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('applies_to_all_types');
        });
    }
};
