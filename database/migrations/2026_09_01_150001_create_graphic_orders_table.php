<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grafikauftrag je Projekt (1:n), entspricht Viettos grafikerstellung.
     * Nur die für Filter/Anzeige relevanten Felder – Zuweisung/Illustrator/
     * Workflow-Details folgen erst, falls Grafikaufträge als eigenes Feature
     * gebaut werden.
     */
    public function up(): void
    {
        Schema::create('graphic_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('graphic_order_status_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graphic_orders');
    }
};
