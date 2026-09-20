<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Weitere Optionen" für Zusatzfelder (Ralf, 2026-09-20/21):
 * - default_value: Vorbelegung beim Anlegen eines neuen Projekts/Dokuments
 * - required: Pflichtfeld (muss beim Speichern ausgefüllt sein)
 * - log_changes: Änderungen in den Vorgängen protokollieren (alt -> neu)
 * - unit: Einheit/Suffix hinter dem Wert ("Stück", "mm")
 *
 * Die Kundenversion behält ihren bisherigen Startwert "1" jetzt als ganz normale
 * Vorbelegung (vorher fest im Code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->string('default_value', 255)->nullable()->after('increments_on_new_version');
            $table->boolean('required')->default(false)->after('default_value');
            $table->boolean('log_changes')->default(false)->after('required');
            $table->string('unit', 30)->nullable()->after('log_changes');
        });

        DB::table('attributes')->where('key', 'kundenversion')->where('system', false)->update(['default_value' => '1']);
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['default_value', 'required', 'log_changes', 'unit']);
        });
    }
};
