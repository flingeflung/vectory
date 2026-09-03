<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standard-Märkte-Set (mandantenscoped), entspricht Viettos laendersets.
     * Wird von Admins verwaltet (Verwaltungs-UI folgt separat), im Projekt
     * nur ausgewählt und per "Go" auf die Markt-Zuweisung angewendet.
     */
    public function up(): void
    {
        Schema::create('market_sets', function (Blueprint $table) {
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
        Schema::dropIfExists('market_sets');
    }
};
