<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-28: "Verknüpfte Jobs" und "Prozentuale Aufteilung" hingen
     * bisher an project.edit - zu breit, das sollte PL-Aufgabe sein, nicht
     * jede TR-Person mit allgemeinem Bearbeitungsrecht koennen (konkreter
     * Fall: Patrick als TR konnte es aendern). Neues, eigenes Recht dafuer -
     * ohne dieses Recht sind beide Reiter weiterhin SICHTBAR (nur lesend),
     * gebucht werden kann ohnehin unabhaengig davon von jedem
     * (project.hours.person_breakdown/project.view betreffen etwas anderes).
     * Startet bei niemandem automatisch, Ralf weist es selbst zu.
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            'key' => 'project.jobload.manage',
            'label' => 'Zeiterfassung: Verknüpfte Jobs & Prozentuale Verteilung ändern',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'project.jobload.manage')->delete();
    }
};
