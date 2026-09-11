<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Projekte kopieren: max. Anzahl Kopien" (Ralf, 2026-09-11) - pro Kunde
     * einstellbar (1-50), begrenzt wie oft ein Projekt in einem Rutsch
     * kopiert werden kann.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_project_copies')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('max_project_copies');
        });
    }
};
