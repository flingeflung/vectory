<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 2 der Zeiterfassung/Ressourcenplanung-Idee (Ralf, 2026-09-27, siehe
 * Roadmap-Backlog): Stunden direkt im Projekt buchen. Bewusst KEINE zweite
 * Tabelle - dieselbe job_hours-Tabelle wie die bisherige Zeiterfassung,
 * nur mit optionalem Projektbezug. Klassische Buchung über die
 * Zeiterfassung lässt project_id immer leer (kein Auswahlzwang bei
 * mehreren verknüpften Projekten, siehe Konzept-Diskussion); die
 * projektbezogene Buchung setzt sie immer. "Projekt gesetzt oder nicht"
 * ist damit schon die vollständige Kennzeichnung, keine eigene Spalte
 * nötig - Management-Auswertung zählt einfach alle Zeilen, Kundenabrechnung
 * nur die mit project_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_hours', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('job_type_id')->constrained()->cascadeOnDelete();
            // project_id NULL zählt in MySQLs Unique-Index als "immer verschieden" - kein Problem,
            // da JobloadController::saveHours() für die klassische Buchung (project_id null) ohnehin
            // per "erst löschen, dann neu einfügen" statt Upsert arbeitet, nie auf diese Eindeutigkeit
            // angewiesen. Für die projektbezogene Buchung ist der Index der eigentliche Schutz: eine
            // Buchung pro Projekt/Job/Tag. Erst NEU anlegen, dann ALT löschen (nicht umgekehrt) - sonst
            // fehlt kurzzeitig jeder Index mit person_id als erster Spalte, den die Fremdschlüssel-
            // Constraint auf person_id zwingend braucht (MySQL-Fehler 1553).
            $table->unique(['person_id', 'job_type_id', 'work_date', 'project_id']);
            $table->dropUnique(['person_id', 'job_type_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::table('job_hours', function (Blueprint $table) {
            // Gleiche Reihenfolge-Regel wie in up(): erst den Ersatzindex anlegen, dann den
            // aktuellen löschen, nie dazwischen ganz ohne person_id-Index dastehen.
            $table->unique(['person_id', 'job_type_id', 'work_date']);
            $table->dropUnique(['person_id', 'job_type_id', 'work_date', 'project_id']);
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
