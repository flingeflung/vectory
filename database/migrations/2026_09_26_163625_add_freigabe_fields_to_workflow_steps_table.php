<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzung zur bereits vorhandenen Sonderfunktion "Freigabe"
 * (WorkflowStep::SPECIAL_BUTTONS['wfs_freigabe'], bisher ohne Wirkung) -
 * Ralf, 2026-09-26: externe Projektbeteiligte bekommen bei Auslösen eines
 * Freigabe-Schritts eine Mail mit Freigabe-/Korrektur-Link, ganz ohne
 * Login. js_function === 'wfs_freigabe' ist bereits der "ist Freigabe-
 * Schritt"-Marker, hier fehlt nur noch: welcher WFS folgt nach erteilter
 * Freigabe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreignId('after_freigabe_workflow_step_id')->nullable()->after('js_function_param')
                ->constrained('workflow_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('after_freigabe_workflow_step_id');
        });
    }
};
