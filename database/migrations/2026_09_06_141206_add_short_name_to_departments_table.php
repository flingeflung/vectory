<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kürzel für Abteilungen - erscheint bei Personen in Rechte-/
     * Funktionsgruppen-Verwaltung neben dem Namen in Klammern (grau, mit
     * vollem Abteilungsnamen als Tooltip), siehe Ralfs Anforderung.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('short_name', 10)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
    }
};
