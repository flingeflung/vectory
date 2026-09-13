<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mini-PIM (Ralf, 2026-09-13, "Produkte"-Nav): Produktgruppen wie
     * Viettos produktgruppen-Tabelle (strPEshort/strPE), aber pro Kunde -
     * die echten Daten kommen später per PIM-Schnittstelle, bis dahin
     * eigene Tabellen mit Testdaten (siehe GenerateTestProducts-Befehl).
     */
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 10);
            $table->string('name', 191);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_groups');
    }
};
