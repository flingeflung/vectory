<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status-Katalog für Grafikaufträge, mandantenscoped. Entspricht Viettos
     * grafikerstellung_status. "is_open" entspricht blnIsOffen (Auftrag noch
     * in Bearbeitung). "is_discarded" markiert den einen Sonderstatus
     * "Verworfen" (Vietto legacy_id 8) – ein verworfener Auftrag zählt in
     * Vietto NICHT als "Grafikauftrag vorhanden".
     */
    public function up(): void
    {
        Schema::create('graphic_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->boolean('is_open')->default(false);
            $table->boolean('is_discarded')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graphic_order_statuses');
    }
};
