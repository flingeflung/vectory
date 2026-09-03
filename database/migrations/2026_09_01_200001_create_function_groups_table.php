<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Funktionsgruppe (Techn. Redaktion, PM/PT, Lektorat, ...), mandantenscoped.
     * Entspricht Viettos funktionsgruppen. Jeder Workflow-Schritt gehört
     * später zu genau einer Funktionsgruppe.
     */
    public function up(): void
    {
        Schema::create('function_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->string('short_name', 20);
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('function_groups');
    }
};
