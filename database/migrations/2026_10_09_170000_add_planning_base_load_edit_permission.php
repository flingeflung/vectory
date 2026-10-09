<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Eigenes Recht zum Ändern der Grundlast (Ralf, 2026-10-09): Ansehen bleibt am Planungsrecht (planning.view), Anlegen, Ändern, Löschen und
     * Kopieren der Grundlast (Basis und je Person) verlangt zusätzlich dieses Recht. Damit nach dem Einspielen niemand überraschend die Bearbeitung
     * verliert, erhalten es zunächst alle Rechte-Vorlagen, die planning.view enthalten; Ralf nimmt es dort heraus, wo es nicht hingehört.
     */
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'key' => 'planning.base_load.edit',
            'label' => 'Planung: Grundlast anlegen, ändern und löschen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $editId = DB::table('permissions')->where('key', 'planning.base_load.edit')->value('id');
        $viewId = DB::table('permissions')->where('key', 'planning.view')->value('id');
        if ($editId && $viewId) {
            $now = now();
            foreach (DB::table('permission_template_permission')->where('permission_id', $viewId)->pluck('permission_template_id') as $templateId) {
                DB::table('permission_template_permission')->insertOrIgnore([
                    'permission_template_id' => $templateId,
                    'permission_id' => $editId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'planning.base_load.edit')->delete();
    }
};
