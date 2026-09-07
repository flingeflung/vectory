<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Installations-weite Einstellungen, OBERHALB aller Mandanten (bewusst
     * kein tenant_id - anders als "settings", siehe Setting-Model). Nur für
     * Super-Admin sichtbar/änderbar (Admin > Superadmin-Reiter). Erster
     * Fall: Lizenzmodell-Schalter "Mandantenfähigkeit an/aus" (Ralfs
     * Modell 1 DL-Installation vs. Modell 2 Einzelkunde-Installation).
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
