<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Modell/System muss editierbar sein. Das wird
     * später sowieso über ein PIM gefüllt, aber die Caption kann beim
     * Kunden variieren" - wandert wie Baujahr/Initiator/Lokalisierung
     * (Migration 2026_09_10_110002) von einem festen system=true-Feld zu
     * einem normalen, pro Kunde umbenennbaren Zusatzfeld.
     */
    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            // Die vorher per seedSystemAttributes()/Migration angelegte
            // system=true-Zeile entfernen, bevor die normale Zusatzfeld-Zeile
            // mit demselben key angelegt wird (sonst Kollision).
            DB::table('attributes')->where('tenant_id', $tenantId)->where('key', 'system_model')->where('system', true)->delete();

            $nextSort = 1 + (int) DB::table('attributes')->where('tenant_id', $tenantId)->where('section', 'stammdaten')->max('sort');

            DB::table('attributes')->insert([
                'tenant_id' => $tenantId,
                'section' => 'stammdaten',
                'system' => false,
                'key' => 'system_model',
                'label' => 'Modell/System',
                'data_type' => 'text',
                'sort' => $nextSort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $projects = DB::table('projects')->where('tenant_id', $tenantId)->whereNotNull('system_model')->select('id', 'attributes', 'system_model')->get();
            foreach ($projects as $project) {
                $values = json_decode($project->attributes ?? '{}', true) ?? [];
                $values['system_model'] = $project->system_model;
                DB::table('projects')->where('id', $project->id)->update(['attributes' => json_encode($values)]);
            }
        }

        if (! Schema::hasColumn('projects', 'attributes_system_model')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('attributes_system_model', 191)
                    ->storedAs("JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.system_model'))")
                    ->nullable()
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'attributes_system_model')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropIndex('projects_attributes_system_model_index');
                $table->dropColumn('attributes_system_model');
            });
        }

        DB::table('attributes')->where('key', 'system_model')->where('system', false)->delete();
    }
};
