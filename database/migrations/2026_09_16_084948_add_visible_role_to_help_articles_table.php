<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            // null = für alle eingeloggten Nutzer sichtbar (Standard,
            // Ralf: "Default für alle sichtbar"), 'admin' = Admin +
            // Super-Admin, 'super_admin' = nur Super-Admin. Dieselben
            // Stufen wie die access-admin/access-superadmin-Gates.
            $table->string('visible_role')->nullable()->after('route_names');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('visible_role');
        });
    }
};
