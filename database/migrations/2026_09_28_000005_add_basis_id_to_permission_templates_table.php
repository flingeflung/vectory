<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-28: die Baustein-Loesung allein reicht nicht - "TR + PL"
     * wurde bisher per einmaliger KOPIE von TR angelegt, aenderte sich TR
     * spaeter, musste "TR + PL" von Hand nachgezogen werden. Fehlendes
     * Stueck: ein Set kann zusaetzlich genau EIN anderes Set als "Basis"
     * LEBEND referenzieren (nicht kopieren) - effectivePermissions() liest
     * dann rekursiv auch die der Basis mit. nullOnDelete: wird die Basis
     * geloescht, bleibt das abgeleitete Set bestehen (nur ohne die
     * geerbten Rechte), statt mitgeloescht zu werden - siehe aber auch
     * PermissionController::destroy(), das ein Loeschen einer noch
     * referenzierten Basis von vornherein blockiert.
     */
    public function up(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->foreignId('basis_id')->nullable()->after('is_baustein')
                ->constrained('permission_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('basis_id');
        });
    }
};
