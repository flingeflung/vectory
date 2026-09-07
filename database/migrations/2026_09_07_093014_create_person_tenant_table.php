<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zusätzliche Mandanten (Kunden), auf die eine Person umschalten darf -
     * neben ihrem eigenen Heimat-Mandanten (people.tenant_id). Bewusst eine
     * eigene, mandantenlose Pivot-Tabelle (spannt per Definition mehrere
     * Mandanten auf), verwaltet in der Personenverwaltung (Ralf: "Der Admin
     * kann in der Personenverwaltung konfigurieren, welcher MA beim DL
     * welche Kundendaten sehen kann"). Nur relevant, wenn Mandantenfähigkeit
     * aktiv ist (siehe SystemSetting).
     */
    public function up(): void
    {
        Schema::create('person_tenant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['person_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_tenant');
    }
};
