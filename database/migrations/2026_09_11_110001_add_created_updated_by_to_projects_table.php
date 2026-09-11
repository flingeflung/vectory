<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-11: Footer "angelegt durch"/"zuletzt geändert" analog
     * Vietto (InitUserID/dtgInitDate, ModUserID/dtgModDate) - Archivstatus
     * bewusst außen vor gelassen ("dann lass den Archivstatus erst mal
     * raus"). Wer/wann kommt fest ans Projekt, nicht in die
     * Projektattribute-Verwaltung (kein Attribute-Eintrag, kein system=true
     * Feld) - siehe ProjectObserver für die automatische Befüllung.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('attributes')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('updated_by_user_id');
        });
    }
};
