<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Bemerkungen" und "Änderungen zur Vorversion" (Ralf, 2026-09-12,
     * Vietto-Vorbild: EINE Tabelle "bemerkungen" mit intTyp 1/2) - hier
     * als eigene Tabelle statt eines einzelnen Freitextfelds, weil Ralf
     * ausdrücklich will, dass jeder einzelne Eintrag Autor + Zeitpunkt
     * trägt (Vietto S1: Liste statt ein großes Textfeld). "type"
     * unterscheidet die beiden Verwendungen (siehe ProjectNote). Kein
     * Freigabeprozess (Ralf: "wie eine E-Mail, die wird ja auch nicht
     * freigegeben") - Einträge werden nur angelegt/gelöscht, nie
     * bearbeitet, daher kein updated_at nötig.
     */
    public function up(): void
    {
        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('text', 2000);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notes');
    }
};
