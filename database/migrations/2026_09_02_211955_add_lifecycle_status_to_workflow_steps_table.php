<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kastenfarbe der Box-Ansicht, entspricht Viettos workflowsteps2.intStatus
     * (1=Geplant, 2=In Bearbeitung, 3=Beendet, 4=Verworfen - siehe
     * $wfs_bgcolors in Viettos incl_functions.php). Komplett unabhängig von
     * is_start/is_end (die sind für die Projekt-Start-/Enddatum-Auswahl) und
     * von is_current auf der Projekt-Instanz (der tatsächliche Fortschritt).
     */
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->tinyInteger('lifecycle_status')->default(2)->after('is_end');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('lifecycle_status');
        });
    }
};
