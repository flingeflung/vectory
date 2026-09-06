<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kürzel (Vietto: strShortname) - erstmal nur das Feld, Validierung/
     * Eindeutigkeits-Regeln folgen noch (Ralf kündigt das gesondert an).
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('short_name', 20)->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
    }
};
