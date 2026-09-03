<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bereich/Abteilung einer Person, entspricht Viettos personen_bereiche.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
