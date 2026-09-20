<?php

use App\Support\StammId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versionierung mit "Stamm-ID" (Ralf, 2026-09-20): alle Versionen desselben
 * Dokuments teilen eine Stamm-ID, stamm_position ist die Reihenfolge in der
 * Kette. Bestehende Projekte bekommen je eine EIGENE Stamm-ID (Kette der
 * Länge 1) - eine Verkettung des Alt-Bestands übernimmt der Vietto-Import
 * bzw. ein Migrationsschema je Kunde, nicht diese Migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('stamm_id', 16)->nullable()->after('source_pn');
            $table->unsignedInteger('stamm_position')->default(1)->after('stamm_id');
        });

        DB::table('projects')->select('id')->orderBy('id')->chunkById(500, function ($projects) {
            foreach ($projects as $project) {
                DB::table('projects')->where('id', $project->id)->update(['stamm_id' => StammId::generate()]);
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            // Kein Unique-Index: die ganze Versionskette teilt dieselbe ID.
            $table->index('stamm_id');
        });

        DB::table('permissions')->insert([
            'key' => 'project.stamm_id.manage',
            'label' => 'Projekt: Stamm-ID lösen bzw. ändern (Versionskette)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'project.stamm_id.manage')->delete();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['stamm_id']);
            $table->dropColumn(['stamm_id', 'stamm_position']);
        });
    }
};
