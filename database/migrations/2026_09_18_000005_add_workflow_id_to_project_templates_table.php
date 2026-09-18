<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 3 der Kapa-Planung (Ralf-Korrektur, 2026-09-18): die Reihenfolge
     * ist erst Workflow koppeln, DADURCH ergeben sich die Funktionsgruppen -
     * nicht umgekehrt. nullOnDelete statt cascade: ein gelöschter Workflow
     * soll die Schablone nicht mitreißen, nur die Kopplung lösen.
     */
    public function up(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->foreignId('workflow_id')->nullable()->after('format')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_id');
        });
    }
};
