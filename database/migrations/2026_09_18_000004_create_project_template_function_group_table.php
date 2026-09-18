<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Step 2 der Kapa-Planung (Ralf, 2026-09-18, siehe Roadmap-Backlog):
     * geplante Stunden je Funktionsgruppe an einer Projektschablone - noch
     * manuell zusammengestellt (welche Fktgrp überhaupt beteiligt sind),
     * Step 3 leitet das später stattdessen aus der gekoppelten Workflow-
     * Zuordnung ab.
     */
    public function up(): void
    {
        Schema::create('project_template_function_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_hours', 6, 1);
            $table->timestamps();

            $table->unique(['project_template_id', 'function_group_id'], 'pt_function_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_template_function_group');
    }
};
