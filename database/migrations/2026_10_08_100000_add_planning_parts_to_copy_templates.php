<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planungs-Bereiche der Projektkopie-Vorlage (Ralf, 2026-10-08): zusätzlich zu den Feldern kann eine Vorlage festlegen, ob Dauern/Sperren der
 * Schritte, Aufwandsprofil bzw. eigene Planstunden und Termine der Schritte mitkopiert werden (Schlüssel siehe App\Services\PlanningTransfer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copy_templates', function (Blueprint $table) {
            $table->json('planning_parts')->nullable()->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('copy_templates', function (Blueprint $table) {
            $table->dropColumn('planning_parts');
        });
    }
};
