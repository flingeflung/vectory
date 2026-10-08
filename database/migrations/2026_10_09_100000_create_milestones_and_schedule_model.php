<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ablaufplan-Umbau Stufe 2 (docs/ablaufplan-konzept.md, Anhang A): Kennzeichen für das Terminmodell am Projekt
 * (1 = alt, 2 = neu) und die Meilensteine (Vorlage am Workflow, Kopie am Projekt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('schedule_model')->default(1);
        });

        Schema::create('workflow_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->string('anchor_type', 20);
            $table->foreignId('anchor_workflow_step_id')->nullable()->constrained('workflow_steps')->cascadeOnDelete();
            $table->integer('offset_days')->default(0);
            $table->boolean('is_market_launch')->default(false);
            $table->string('check_direction', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_milestone_id')->nullable()->constrained('workflow_milestones')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->string('anchor_type', 20);
            $table->foreignId('anchor_workflow_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->integer('offset_days')->default(0);
            $table->date('fixed_date')->nullable();
            $table->boolean('is_market_launch')->default(false);
            $table->string('check_direction', 20)->nullable();
            $table->date('date')->nullable();
            $table->dateTime('reached_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('workflow_milestones');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('schedule_model');
        });
    }
};
