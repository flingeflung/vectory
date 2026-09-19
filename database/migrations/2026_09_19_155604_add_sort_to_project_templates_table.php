<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-19: Projektschablonen im Admin per Drag & Drop sortierbar.
     * Startreihenfolge = bisherige feste Reihenfolge (Wochen vor Monaten, dann
     * Dauer, dann Name), damit sich beim Einspielen nichts verschiebt.
     */
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->unsignedInteger('sort')->default(0)->after('name');
        });

        foreach (DB::table('project_templates')->distinct()->pluck('tenant_id') as $tenantId) {
            $ids = DB::table('project_templates')->where('tenant_id', $tenantId)
                ->orderByRaw("FIELD(duration_unit, 'weeks', 'months')")->orderBy('duration_value')->orderBy('name')->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table('project_templates')->where('id', $id)->update(['sort' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
