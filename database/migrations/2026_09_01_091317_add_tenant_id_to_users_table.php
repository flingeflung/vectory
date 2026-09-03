<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Bestandsdaten: ein Default-Mandant reicht für Step 1 (siehe CLAUDE.md).
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => 'Standardmandant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
