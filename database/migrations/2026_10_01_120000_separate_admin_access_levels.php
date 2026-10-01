<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin','organization_admin','central_admin','super_admin') NOT NULL DEFAULT 'user'");
        }

        $homeTenantIds = DB::table('tenants')->where('is_home_tenant', true)->pluck('id');
        DB::table('users')->where('role', 'admin')->whereIn('tenant_id', $homeTenantIds)->update(['role' => 'central_admin']);
        DB::table('users')->where('role', 'admin')->update(['role' => 'organization_admin']);
        DB::table('permission_templates')->where('name', 'Admin')->update(['name' => 'Alle Benutzerrechte']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','organization_admin','central_admin','super_admin') NOT NULL DEFAULT 'user'");
        }

        Schema::table('permission_templates', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->string('role')->default('user')->after('tenant_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin','organization_admin','central_admin','super_admin') NOT NULL DEFAULT 'user'");
        }

        DB::table('users')->whereIn('role', ['organization_admin', 'central_admin'])->update(['role' => 'admin']);
        DB::table('permission_templates')->where('name', 'Alle Benutzerrechte')->update(['name' => 'Admin']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user'");
        }
    }
};
