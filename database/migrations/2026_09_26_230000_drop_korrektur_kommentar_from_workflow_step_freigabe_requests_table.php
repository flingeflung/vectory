<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ralf, 2026-09-26: der Kommentar des externen Prüfers beim Korrektur-Upload
 * gehört nur in die Mail an den TR, nicht in die Datenbank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->dropColumn('korrektur_kommentar');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_step_freigabe_requests', function (Blueprint $table) {
            $table->text('korrektur_kommentar')->nullable()->after('status');
        });
    }
};
