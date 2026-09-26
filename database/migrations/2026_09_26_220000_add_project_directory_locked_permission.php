<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-26: nicht jeder darf ins gesperrte Projektverzeichnis
     * (SV) schauen. Das Arbeitsverzeichnis (AV) ist davon unabhängig für
     * alle sichtbar. Bestehende Admin-Rechte-Sets bekommen das Recht
     * direkt mit (Admins sahen das SV bisher ohnehin), User-Sets nicht.
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'project.directory.locked',
            'label' => 'Gesperrtes Projektverzeichnis (SV) einsehen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('key', 'project.directory.locked')->value('id');

        PermissionTemplate::query()->withoutGlobalScope('tenant')->where('role', 'admin')->get()
            ->each(fn (PermissionTemplate $template) => $template->permissions()->syncWithoutDetaching([$permissionId]));
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'project.directory.locked')->delete();
    }
};
