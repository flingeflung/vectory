<?php

use App\Models\PermissionTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reine visuelle Rangordnung der Rechte-Sets (z.B. "wer hat absteigend
     * die meisten Rechte"), per Drag&Drop gepflegt - kein fachlicher Effekt.
     */
    public function up(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0)->after('name');
        });

        PermissionTemplate::query()->orderBy('role')->orderBy('name')->get()
            ->each(fn (PermissionTemplate $template, int $index) => $template->update(['sort' => $index]));
    }

    public function down(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
