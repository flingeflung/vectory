<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attribute-Verwaltung (Ralf, 2026-09-10): Attribute gehören jetzt zu
     * einem der drei Projekt-Bereiche (analog zur Struktur in
     * projekte/partials/detail.blade.php) statt ausschließlich
     * "typspezifisch" zu sein. Bestehende Zeilen sind alle aus dem
     * Vietto-Import und waren bisher ausschließlich typspezifische
     * Attribute - Default entsprechend gesetzt, keine Datenmigration nötig.
     *
     * "multiple" gilt nur für data_type='select' (Mehrfachauswahl-Pulldown,
     * siehe Ralf: "bei Pulldowns muss Einfach/Mehrfachauswahl möglich
     * sein") - bei jedem anderen Typ bedeutungslos, bleibt false.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->string('section')->default('typspezifisch')->after('tenant_id');
            $table->boolean('multiple')->default(false)->after('data_type');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['section', 'multiple']);
        });
    }
};
