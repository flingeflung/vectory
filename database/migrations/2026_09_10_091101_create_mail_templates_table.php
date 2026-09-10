<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 1 des Mail-Vorlagen-Konzepts (Ralf, 2026-09-10): nur Name + Text
     * mit einfügbaren Projekt-Feld-Platzhaltern (z.B. "{material_number}").
     * Betreff und Empfänger/CC sitzen bewusst NICHT hier - die werden laut
     * Ralf erst im jeweiligen Anwendungsfall festgelegt (Step 2, noch nicht
     * gebaut), da dieselbe Vorlage an unterschiedlichen Stellen mit
     * unterschiedlichen Empfängern/Betreffen genutzt werden können soll.
     */
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
