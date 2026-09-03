<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mandantenscoped Katalog der variablen, projekttyp-abhängigen Attribute
     * (Analogon zu Viettos attribute-Tabelle). "key" ist der stabile,
     * technische Bezeichner, unter dem der Wert in projects.attributes (JSON)
     * liegt; "label" ist der pro Mandant frei änderbare Anzeigename.
     */
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('data_type')->default('text');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
