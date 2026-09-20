<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Kundenversion" (Ralf, 2026-09-20): projects.version wird von einer ganzen
 * Zahl zu Freitext (Kunden haben eigene Nomenklaturen: "V3", "1.2", "B" ...).
 * Für die Sortierung der Spalte bekommt jedes Projekt einen Zahlenschlüssel
 * (version_sort, siehe App\Support\VersionLabel::sortKey()). Vorhandene
 * Zahlen bleiben als Text unverändert stehen; Viettos -1 ("nicht gesetzt")
 * wird zu leer. Der Feldname im Katalog heißt jetzt "Kundenversion" (nur wo
 * noch der Standardname "Version" steht - eigene Umbenennungen bleiben).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('version_sort')->nullable()->after('version');
        });

        DB::table('projects')->where('version', '<', 0)->update(['version' => null]);
        DB::table('projects')->whereNotNull('version')->update(['version_sort' => DB::raw('version * 100000000')]);

        Schema::table('projects', function (Blueprint $table) {
            $table->string('version', 50)->nullable()->change();
            $table->index('version_sort');
        });

        DB::table('attributes')->where('key', 'version')->where('label', 'Version')->update(['label' => 'Kundenversion']);
    }

    public function down(): void
    {
        DB::table('attributes')->where('key', 'version')->where('label', 'Kundenversion')->update(['label' => 'Version']);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['version_sort']);
        });

        // Nicht rein numerische Kundenversionen gehen beim Zurückrollen verloren.
        DB::table('projects')->whereRaw("version NOT REGEXP '^[0-9]+$'")->update(['version' => null]);

        Schema::table('projects', function (Blueprint $table) {
            $table->smallInteger('version')->nullable()->change();
            $table->dropColumn('version_sort');
        });
    }
};
