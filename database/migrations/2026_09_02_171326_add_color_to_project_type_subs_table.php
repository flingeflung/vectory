<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Farbe des Balkens für die typspezifischen Attribute in der
     * Projektdetailansicht (Hex-Code). Pro Mandant änderbar, da jeder
     * Kunde eigene Projektarten mit eigener Farbwahl anlegen kann.
     */
    public function up(): void
    {
        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
