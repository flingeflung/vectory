<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-28: eine Sammelprojekt-Schablone (Plan fuer ein
     * Hauptprojekt mit Unterprojekten unterschiedlicher Workflows) laesst
     * sich nicht sinnvoll an EINEN Workflow koppeln - die Workflow-
     * Kopplung (2026-09-18, siehe ProjectTemplate::relevantFunctionGroups())
     * wuerde sonst Funktionsgruppen anderer, im Verbund ebenfalls
     * vorkommender Workflows ausschliessen. Diese Schablonen bekommen den
     * vollen Funktionsgruppen-Katalog des Mandanten freigeschaltet, ganz
     * unabhaengig von einer etwaigen (dann rein informativen) Workflow-Wahl.
     */
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->boolean('unrestricted_function_groups')->default(false)->after('workflow_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropColumn('unrestricted_function_groups');
        });
    }
};
