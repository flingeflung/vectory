<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Katalog-Erweiterung nach Scan der Vietto-Rechte gegen echten Vectory-
     * Code: diese sechs Aktionen existieren bereits, waren aber komplett
     * ungegated. Anders als die ersten drei Rechte (bewusst Admin-only)
     * sind das Alltags-Fähigkeiten - werden deshalb allen bestehenden
     * Admin- UND User-Sets direkt mitgegeben, damit niemand durch diese
     * Migration plötzlich ausgesperrt wird.
     */
    public function up(): void
    {
        $permissions = [
            ['key' => 'project.view', 'label' => 'Projekt-Details überhaupt aufrufen'],
            ['key' => 'project.edit', 'label' => 'Projekt-Stammdaten bearbeiten'],
            ['key' => 'project.people.manage', 'label' => 'Projektbeteiligte hinzufügen/entfernen'],
            ['key' => 'graphic_order.create', 'label' => 'Illustrationsauftrag anlegen'],
            ['key' => 'workflow_step.activate', 'label' => 'Workflow-Schritt aktivieren'],
            ['key' => 'workflow_step.due_date', 'label' => 'Fälligkeitsdatum eines Workflow-Schritts ändern'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                ...$permission,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $newPermissionIds = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id');

        PermissionTemplate::query()->whereIn('role', ['admin', 'user'])->get()->each(function (PermissionTemplate $template) use ($newPermissionIds) {
            $template->permissions()->syncWithoutDetaching($newPermissionIds);
        });
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('key', [
            'project.view', 'project.edit', 'project.people.manage',
            'graphic_order.create', 'workflow_step.activate', 'workflow_step.due_date',
        ])->delete();
    }
};
