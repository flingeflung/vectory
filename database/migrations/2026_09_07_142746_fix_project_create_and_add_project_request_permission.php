<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf: Personen, die nicht hauptsächlich mit dem Tool arbeiten, gehen
     * schludrig mit dem Anlegen um (Duplikate, keine Vorabprüfung usw.) -
     * project.create ist deshalb bewusst KEINE Alltags-Fähigkeit mehr,
     * anders als ursprünglich angenommen (Migration
     * 2026_09_07_131759_add_project_create_permission). Wird aus dem
     * Standard-"User"-Set wieder entfernt - Admin behält es (Admin ist
     * bereits mit allem betraut). Wer es nicht hat, stellt stattdessen
     * eine Projektanfrage (neues Recht project.request, Vietto-Vorbild:
     * "Projektanfrage an die TR" - formlose Anfrage statt echter Anlage).
     */
    public function up(): void
    {
        $createId = DB::table('permissions')->where('key', 'project.create')->value('id');

        if ($createId) {
            PermissionTemplate::query()->where('role', 'user')->get()->each(function (PermissionTemplate $template) use ($createId) {
                $template->permissions()->detach($createId);
            });
        }

        DB::table('permissions')->insert([
            'key' => 'project.request',
            'label' => 'Projektanfrage stellen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = DB::table('permissions')->where('key', 'project.request')->value('id');

        PermissionTemplate::query()->whereIn('role', ['admin', 'user'])->get()->each(function (PermissionTemplate $template) use ($requestId) {
            $template->permissions()->syncWithoutDetaching([$requestId]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $createId = DB::table('permissions')->where('key', 'project.create')->value('id');

        if ($createId) {
            PermissionTemplate::query()->where('role', 'user')->get()->each(function (PermissionTemplate $template) use ($createId) {
                $template->permissions()->syncWithoutDetaching([$createId]);
            });
        }

        DB::table('permissions')->where('key', 'project.request')->delete();
    }
};
