<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merkt sich den zuletzt aktiven Kunden pro User dauerhaft (DB statt
     * nur Session) - Ralf: nach dem Einloggen startet man sonst immer mit
     * dem eigenen Heimat-Mandanten statt dem zuletzt genutzten Kunden,
     * weil die Session bei jedem Login neu ist. Siehe CurrentTenant::id().
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_active_tenant_id')->nullable()->after('tenant_id')->constrained('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_active_tenant_id');
        });
    }
};
