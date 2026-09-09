<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf: das Publikationsdatum soll nur die TR ändern dürfen - sie ist
     * für das Publizieren zuständig (Vietto-Vorbild: wird dort manuell
     * eingetragen und in den Vorgängen protokolliert, siehe
     * ProjectController::update()). Anders als die "Alltags"-Rechte in der
     * vorigen Migration bewusst NICHT an alle User-Sets vergeben, nur an
     * Admin (wie immer alles) und an Rechte-Sets, die "TR" heißen -
     * Namensabgleich statt fixer ID, da PermissionTemplate pro Mandant
     * eigene, frei benannte Sets hat. Andere Mandanten/Sets müssen das Recht
     * bei Bedarf selbst über die Rechte-Verwaltung nachziehen.
     */
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'key' => 'project.publication_date.edit',
            'label' => 'Publikationsdatum ändern',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PermissionTemplate::query()
            ->where(fn ($query) => $query->where('role', 'admin')->orWhere('name', 'TR'))
            ->get()
            ->each(fn (PermissionTemplate $template) => $template->permissions()->syncWithoutDetaching([$permissionId]));
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'project.publication_date.edit')->delete();
    }
};
