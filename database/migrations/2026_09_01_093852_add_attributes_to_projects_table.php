<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variable, projekttyp-abhängige Attribute (Analogon zu Viettos
     * attribute/attribute_projekttyp_cx) landen hier als JSON statt in einer
     * separaten EAV-Tabelle – ein Projekt bleibt eine Zeile, kein Join-Fan-out
     * beim Filtern/Sortieren. Gezielte Performance für einzelne Attribute
     * kommt über generierte, indizierte Spalten (siehe nächste Migration).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('attributes')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });
    }
};
