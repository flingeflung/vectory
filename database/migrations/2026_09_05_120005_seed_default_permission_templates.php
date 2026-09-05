<?php

use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Legt je Tenant die zwei Standard-Sets "Admin" und "User" an (Gast
     * folgt später) und ordnet alle bestehenden Personen mit Login gemäß
     * ihrer aktuellen User::role zu. Admin startet mit allen bisher
     * bekannten Rechten (bisheriges Verhalten), User bewusst leer - Admin 2
     * entscheidet künftig selbst, was User-Sets dürfen.
     */
    public function up(): void
    {
        $permissionIds = Permission::query()->pluck('id');

        Tenant::query()->pluck('id')->each(function (int $tenantId) use ($permissionIds) {
            $adminTemplate = PermissionTemplate::query()->create([
                'tenant_id' => $tenantId,
                'role' => 'admin',
                'name' => 'Admin',
            ]);
            $adminTemplate->permissions()->sync($permissionIds);

            $userTemplate = PermissionTemplate::query()->create([
                'tenant_id' => $tenantId,
                'role' => 'user',
                'name' => 'User',
            ]);

            User::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('role', ['admin', 'user'])
                ->whereNotNull('person_id')
                ->get()
                ->each(function (User $user) use ($adminTemplate, $userTemplate) {
                    $user->person->update([
                        'permission_template_id' => $user->role === 'admin' ? $adminTemplate->id : $userTemplate->id,
                    ]);
                });
        });
    }

    public function down(): void
    {
        PermissionTemplate::query()->delete();
    }
};
