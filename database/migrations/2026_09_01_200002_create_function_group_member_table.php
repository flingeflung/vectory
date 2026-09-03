<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Personen-Pool je Funktionsgruppe (wer kommt für diese Funktionsgruppe
     * grundsätzlich infrage), entspricht Viettos funktionsgruppenmembers.
     */
    public function up(): void
    {
        Schema::create('function_group_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['function_group_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('function_group_member');
    }
};
