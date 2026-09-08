<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf: eine Art (oder Kategorie) kann irgendwann nicht mehr für neue
     * Projekte angeboten werden sollen, historische Projekte müssen sie
     * aber weiterhin korrekt anzeigen - deshalb ein/aus statt löschen.
     */
    public function up(): void
    {
        Schema::table('project_type_mains', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('name');
        });

        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('project_type_mains', function (Blueprint $table) {
            $table->dropColumn('active');
        });

        Schema::table('project_type_subs', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
