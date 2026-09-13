<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verknüpfung Projekt<->Produkt (Ralf, 2026-09-13, analog Viettos
     * projekte_pim_cx) - mehrere Produkte pro Projekt möglich. Kein
     * tenant_id nötig (anders als project_checklists o.ä.): beide FKs sind
     * schon über ihre eigenen Tabellen mandantengebunden, project_id und
     * product_id aus verschiedenen Mandanten kann es nicht geben.
     */
    public function up(): void
    {
        Schema::create('project_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_products');
    }
};
