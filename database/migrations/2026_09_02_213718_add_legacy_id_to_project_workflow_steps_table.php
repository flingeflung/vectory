<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fehlte bisher: die ursprüngliche workflowprogress2.valID. Wird u.a.
     * von workflow_pers_cx2.valWFStepID referenziert (verweist NICHT auf die
     * WFS-Schablone workflowsteps2, sondern auf die Projekt-Instanz-Zeile!)
     * - ohne das keine Zuordnung der Personen-pro-WFS-Importe möglich.
     */
    public function up(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table) {
            $table->integer('legacy_id')->nullable()->after('workflow_step_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table) {
            $table->dropColumn('legacy_id');
        });
    }
};
