<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aus Vietto übernommenes Flag (workflowsteps2.blnDauerAenderbar), dort
     * aber selbst nie ausgewertet (nur kopiert, nie geprüft) - und auch
     * Ralf sieht keinen Grund, warum die Dauer eines WFS gesperrt sein
     * sollte. Also raus statt eine nie gebrauchte Sperre nachzubauen.
     */
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('duration_editable');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->boolean('duration_editable')->default(true);
        });
    }
};
