<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Markiert den EINEN Mandanten, der den Dienstleister selbst
     * repräsentiert (Ralf, 2026-09-18: "Heimat-Admin"/"Kundekunde-Admin"
     * als getrennte Rollen statt einem globalen "admin"). Ein Admin dieses
     * Mandanten bekommt Vollzugriff auf ALLE Mandanten (CurrentTenant::
     * userCanAccess()), ein Admin jedes anderen Mandanten bleibt auf den
     * eigenen Mandanten beschränkt - siehe PersonController für die
     * Bearbeitungs-Sperre bei ausgeliehenen Personen.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_home_tenant')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('is_home_tenant');
        });
    }
};
