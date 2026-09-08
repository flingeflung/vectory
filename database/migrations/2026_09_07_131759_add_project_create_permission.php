<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Projekt neu anlegen" ist eine neue Vectory-Fähigkeit (Vietto-Analyse
     * ergab: dort gibt es dafür KEIN eigenes Recht, nur eine UI-Sperre über
     * die Rolle - bewusst nicht übernommen, siehe Rechtekonzept-Prinzip
     * "jede neue Aktion braucht einen Katalog-Eintrag"). Alltags-Fähigkeit
     * wie graphic_order.create - allen bestehenden Admin- UND User-Sets
     * direkt mitgegeben.
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'project.create',
            'label' => 'Projekt neu anlegen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('key', 'project.create')->value('id');

        PermissionTemplate::query()->whereIn('role', ['admin', 'user'])->get()->each(function (PermissionTemplate $template) use ($permissionId) {
            $template->permissions()->syncWithoutDetaching([$permissionId]);
        });
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'project.create')->delete();
    }
};
