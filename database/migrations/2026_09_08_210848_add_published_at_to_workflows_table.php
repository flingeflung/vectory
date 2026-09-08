<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ersetzt Workflow::isPublished() als reine Nutzungs-Erkennung (irgendein
     * Projekt hat irgendeinen Schritt schon mal instanziiert) durch ein
     * explizites Flag. Grund (Ralf, konkreter Test-Fall): die alte Logik
     * sperrte einen frischen Workflow schon beim ERSTEN Test-Zuweisen an ein
     * Projekt - genau dann, wenn man noch am Ausprobieren/Korrigieren ist.
     * Jetzt bleibt ein Workflow beliebig oft testweise zuweisbar UND frei
     * bearbeitbar, bis man ihn bewusst über "Veröffentlichen" sperrt.
     *
     * Backfill: alle Workflows, die nach der ALTEN Logik schon "benutzt"
     * waren (echte, historisch gewachsene Vietto-Daten + bereits gebaute
     * neue Versionen), werden sofort als veröffentlicht markiert - sonst
     * wären deren Inhalte plötzlich wieder änderbar, obwohl echte Projekte
     * längst darauf zeigen.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('active');
        });

        DB::table('workflows')
            ->whereIn('id', function ($query) {
                $query->select('workflow_steps.workflow_id')
                    ->from('project_workflow_steps')
                    ->join('workflow_steps', 'workflow_steps.id', '=', 'project_workflow_steps.workflow_step_id')
                    ->distinct();
            })
            ->update(['published_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
