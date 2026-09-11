<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Projekte kopieren" (Ralf, 2026-09-11): pro Kunde beliebig viele
     * Vorlagen, jede legt fest, welche Felder beim Kopieren übernommen
     * werden (siehe copy_template_attribute) - im Unterschied zu Vietto,
     * das beim Kopieren blind (fast) alles überträgt.
     */
    public function up(): void
    {
        Schema::create('copy_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_templates');
    }
};
