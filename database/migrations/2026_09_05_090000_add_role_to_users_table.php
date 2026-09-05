<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ebene 1/2 des Rollenmodells (siehe CLAUDE.md) - Super-Admin (alles,
     * mandantenübergreifend) und Admin (alles, aber nur im eigenen
     * Mandanten). Ebene 3 (normaler User) ist der Default und läuft über
     * das Funktionsgruppen-Rechte-System (siehe permissions-Migration),
     * nicht über dieses Feld.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'admin', 'super_admin'])->default('user')->after('tenant_id');
        });

        // Bestandsdaten: das bisher einzige echte Konto wird Super-Admin.
        DB::table('users')->where('email', 'ergetr65@googlemail.com')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
