<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vietto erlaubt negative intAnzBilder-Werte (vereinzelt in den Echtdaten
     * vorhanden, vermutlich Erfassungsfehler) - unsigned war zu eng.
     */
    public function up(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->smallInteger('image_count')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('image_count')->default(0)->change();
        });
    }
};
