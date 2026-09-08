<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die Reihenfolge kam 1:1 aus Viettos intSort (laender) und wirkte
     * dadurch willkürlich (Ralf: "interessant"). Seit der Markt-Katalog
     * angehakte Länder ohnehin nach oben sortiert, reicht als Rest-Ordnung
     * schlicht alphabetisch (siehe MarketController::index()) - die
     * Vietto-Spalte wird nicht mehr gebraucht.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0);
        });
    }
};
