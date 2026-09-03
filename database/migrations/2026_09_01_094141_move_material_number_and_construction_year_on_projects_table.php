<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Korrektur der Feldklassifikation (siehe Vietto-Formular-Screenshot):
     * - Baujahr ist ein festes/typunabhängiges Feld -> eigene Spalte.
     * - Materialnummer ist typabhängig (nur bei bestimmten Projektarten
     *   relevant) -> gehört ins attributes-JSON, nicht als feste Spalte.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('construction_year', 30)->nullable()->after('system_model');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_attributes_baujahr_index');
            $table->dropColumn('attributes_baujahr');
            $table->dropColumn('material_number');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('attributes_material_number', 30)
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.material_number'))")
                ->nullable()
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_attributes_material_number_index');
            $table->dropColumn('attributes_material_number');
            $table->dropColumn('construction_year');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('material_number')->nullable();
            $table->string('attributes_baujahr', 30)
                ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.baujahr'))")
                ->nullable()
                ->index();
        });
    }
};
