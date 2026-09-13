<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Meine Projektgruppen" (Ralf, 2026-09-13, analog Viettos gruppen/
     * gruppen_cx/gruppen_pers_cx) - persönliche, aber echt TEILBARE
     * Projektlisten (kein Besitzer-Feld, Sichtbarkeit läuft komplett über
     * project_group_user). Bewusst kein description/blnActive-Feld (hat
     * Vietto hier auch nicht, das gibt's nur beim unabhängigen, admin-
     * gepflegten "Projektgruppenzugehörigkeit"-System, das hier NICHT
     * nachgebaut wird).
     */
    public function up(): void
    {
        Schema::create('project_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_groups');
    }
};
