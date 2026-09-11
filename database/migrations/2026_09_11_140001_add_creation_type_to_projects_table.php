<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: "Erstellungsstatus" editierbar machen (war bisher
     * nur Platzhalter, siehe Attribute::SYSTEM_FIELDS). Vietto-Vorbild:
     * einfaches 2-Werte-Feld (projekte.intTyp, 1=Neuerstellung,
     * 2=Änderung) - Werte hier bewusst gleich gehalten, falls das Feld
     * später mal aus Vietto importiert werden soll.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('creation_type')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('creation_type');
        });
    }
};
