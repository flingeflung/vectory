<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-28: Datenschutz-Konflikt geloest - eine Person darf pro
     * Datenschutz-Anforderung nicht "on top" ein zusaetzliches Recht
     * bekommen koennen, ohne ihr Set zu verlieren, aber ein neues eigenes
     * Set nur fuer eine Handvoll Personen zu pflegen war Ralfs Sorge
     * ("verzetteln"). Loesung: zwei Arten von permission_templates -
     * normale "Sets" (weiterhin das EINE, einer Person zugewiesene Set) und
     * "Bausteine" (nie einer Person zuweisbar, nur in beliebig viele Sets
     * einbindbar, siehe permission_template_baustein-Tabelle). Bausteine
     * koennen selbst KEINE weiteren Bausteine einbinden - das verhindert
     * Ringbezuege durch Konstruktion, nicht nur durch Pruefung.
     */
    public function up(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->boolean('is_baustein')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('permission_templates', function (Blueprint $table) {
            $table->dropColumn('is_baustein');
        });
    }
};
