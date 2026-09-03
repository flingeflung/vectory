<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Viettos fachliche "Rolle" (Admin/TR/PM-PT/ÜS-Mgmt/Gast/Prozess),
     * entspricht rollen. Laut CLAUDE.md eine andere Achse als Vectorys
     * künftiges Zugriffsrollen-Modell - hier nur als Referenzfeld an der
     * Person übernommen, NICHT das echte Berechtigungssystem.
     */
    public function up(): void
    {
        Schema::create('legacy_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_roles');
    }
};
