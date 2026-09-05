<?php

use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Admin verlor mit dem Gate::before()-Bypass automatisch alle Rechte -
     * ohne diese Vorbelegung würde jeder bestehende Admin beim Deploy
     * plötzlich nichts mehr dürfen. Super-Admin kann danach in der
     * Rechte-Verwaltung einzelne Häkchen entfernen.
     */
    public function up(): void
    {
        $permissionIds = Permission::query()->pluck('id');

        Tenant::query()->pluck('id')->each(function (int $tenantId) use ($permissionIds) {
            $rows = $permissionIds->map(fn ($permissionId) => [
                'tenant_id' => $tenantId,
                'role' => 'admin',
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DB::table('role_permissions')->insert($rows);
        });
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('role', 'admin')->delete();
    }
};
