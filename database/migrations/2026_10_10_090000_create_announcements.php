<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mitteilungen für die Startseite (Ralf, 2026-10-10): kurze Texte vom Admin, die in der Kachel „Meldungen" erscheinen.
 * Bewusst ohne eigene tenant_id: eine Mitteilung kann mehrere Organisationen erreichen, die Empfänger-Organisationen
 * stehen in announcement_tenant (wandert beim endgültigen Löschen einer Organisation per CASCADE mit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('text', 500);
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_tenant', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->primary(['announcement_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_tenant');
        Schema::dropIfExists('announcements');
    }
};
