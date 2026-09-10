<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktiver Bug, gefunden bei der Attribute-Verwaltungs-Planung (Ralf,
     * 2026-09-10): attribute_project_type hing noch am rohen Vietto-Code
     * project_type_sub statt an der echten project_type_subs.id - für
     * jede direkt in Vectory angelegte Projektart (kein Vietto-Import,
     * z.B. bei _Standardkunde) lief die Zuordnung dadurch ins Leere
     * (Project::relevantAttributes() fand nie etwas). Backfill über
     * project_type_subs.legacy_id, gescoped auf den Mandanten des
     * jeweiligen Attributs (project_type_sub-Codes sind nur innerhalb
     * eines Mandanten eindeutig, nicht global).
     */
    public function up(): void
    {
        Schema::table('attribute_project_type', function (Blueprint $table) {
            $table->foreignId('project_type_sub_id')->nullable()->after('project_type_sub')->constrained('project_type_subs')->cascadeOnDelete();
        });

        DB::statement('
            UPDATE attribute_project_type apt
            INNER JOIN attributes a ON a.id = apt.attribute_id
            INNER JOIN project_type_subs pts ON pts.tenant_id = a.tenant_id AND pts.legacy_id = apt.project_type_sub
            SET apt.project_type_sub_id = pts.id
        ');

        // Zeilen, die sich nicht zuordnen ließen (z.B. Projektart in Vietto
        // gelöscht, aber die Zuordnung nie aufgeräumt) sind ohnehin toter
        // Ballast - ohne project_type_sub_id war die Zeile schon vorher
        // wirkungslos, jetzt konsequent entfernt statt als NULL-Leiche zu
        // bleiben.
        DB::table('attribute_project_type')->whereNull('project_type_sub_id')->delete();

        // Reihenfolge wichtig: attribute_id braucht durchgehend einen
        // Index für seine eigene Fremdschlüsselbindung - erst den neuen
        // Unique-Index anlegen, dann den alten fallen lassen, sonst
        // verweigert MySQL das Dropping ("needed in a foreign key
        // constraint").
        Schema::table('attribute_project_type', function (Blueprint $table) {
            $table->unique(['attribute_id', 'project_type_sub_id']);
        });
        Schema::table('attribute_project_type', function (Blueprint $table) {
            $table->dropUnique(['attribute_id', 'project_type_sub']);
            $table->dropColumn('project_type_sub');
        });
    }

    public function down(): void
    {
        Schema::table('attribute_project_type', function (Blueprint $table) {
            $table->smallInteger('project_type_sub')->nullable()->after('attribute_id');
        });

        DB::statement('
            UPDATE attribute_project_type apt
            INNER JOIN project_type_subs pts ON pts.id = apt.project_type_sub_id
            SET apt.project_type_sub = pts.legacy_id
        ');

        // Gleiche Reihenfolge-Regel wie in up(): erst den Ersatz-Index
        // anlegen, dann den alten (project_type_sub_id-gestützten) fallen
        // lassen.
        Schema::table('attribute_project_type', function (Blueprint $table) {
            $table->unique(['attribute_id', 'project_type_sub']);
        });
        Schema::table('attribute_project_type', function (Blueprint $table) {
            $table->dropUnique(['attribute_id', 'project_type_sub_id']);
            $table->dropConstrainedForeignId('project_type_sub_id');
        });
    }
};
