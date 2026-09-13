<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-13: "Das ist ein sehr gefährliches Feature in den
     * falschen Händen!" - bewusst nur an Admin-Rechte-Sets vergeben, nicht
     * an normale User-Sets (analog dem "Destructive actions"-Muster, siehe
     * z.B. Firma/Abteilung-Löschen mit vorhandenen Werten). Super-Admin
     * hat ohnehin immer alles (Gate::before in AppServiceProvider).
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'key' => 'project.multichange',
            'label' => 'Multichange (mehrere Projekte gleichzeitig ändern)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PermissionTemplate::query()
            ->where('role', 'admin')
            ->get()
            ->each(fn (PermissionTemplate $template) => $template->permissions()->syncWithoutDetaching([$permissionId]));
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'project.multichange')->delete();
    }
};
