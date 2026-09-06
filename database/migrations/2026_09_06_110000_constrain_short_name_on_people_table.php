<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralfs Vorgaben zum Kürzel: 2-4 Zeichen (technisch begrenzt), pro
     * Mandant eindeutig (harte Regel, keine Dopplungen) - Groß-/
     * Kleinschreibung zählt dabei als gleich, das übernimmt schon die
     * utf8mb4_unicode_ci-Kollation der Spalte automatisch (kein
     * Sonderfall im Code nötig). NULL bleibt erlaubt und mehrfach möglich
     * (Kürzel ist optional).
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('short_name', 4)->nullable()->change();
            $table->unique(['tenant_id', 'short_name']);
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'short_name']);
            $table->string('short_name', 20)->nullable()->change();
        });
    }
};
