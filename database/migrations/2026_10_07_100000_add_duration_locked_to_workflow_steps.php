<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gesperrte Dauer am Workflow-Schritt (Ralf, 2026-10-07): in der Planung (Zeitraum-Diagramm) bleibt die Dauer eines gesperrten
 * Schritts beim Verschieben anderer Schritte unverändert, der Schritt wandert mit. Die Sperre am Schritt ist die Voreinstellung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->boolean('duration_locked')->default(false)->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('duration_locked');
        });
    }
};
