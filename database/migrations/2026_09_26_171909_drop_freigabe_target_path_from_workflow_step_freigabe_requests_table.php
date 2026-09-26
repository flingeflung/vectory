<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vereinfachung nach weiterer Ralf-Rücksprache (2026-09-26): kein
 * separates Zielverzeichnis für den Freigabe-Ausgang, nur eins für die
 * Korrektur. Die Quell-Angabe ist außerdem nicht zwingend eine PDF-Datei
 * (kann auch ein Verzeichnis mit mehreren Dateien/anderen Formaten sein,
 * z.B. Illustrationen oder eine PPT) und optional - Spalte entsprechend
 * umbenannt und nullable; der tatsächliche Typ (Datei vs. Ordner) wird
 * zur Laufzeit per is_file()/is_dir() erkannt, keine eigene Spalte nötig.
 * Tabelle ist noch leer (frisch angelegt, lokal), deshalb drop+neu statt
 * ->change() (spart die doctrine/dbal-Abhängigkeit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->dropColumn(['source_pdf_path', 'freigabe_target_path']);
        });
        Schema::table('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->string('source_path', 1000)->nullable()->after('triggered_by_person_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->dropColumn('source_path');
        });
        Schema::table('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->string('source_pdf_path', 1000)->after('triggered_by_person_id');
            $table->string('freigabe_target_path', 1000)->nullable();
        });
    }
};
