<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-10-03: Organisation komplett deaktivieren (z. B. Vertrag gekündigt), ohne etwas zu
     * löschen. Alle bestehenden Organisationen bleiben aktiv. Wirkung siehe Tenant::inactiveIds()
     * und die Schutzregel "tenant_active" in BelongsToTenant.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_home_tenant');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
