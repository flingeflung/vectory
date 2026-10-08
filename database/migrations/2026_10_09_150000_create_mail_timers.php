<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail-Timer (Ralf, 2026-10-09): zeitgesteuerte Erinnerungsmails zu einem Projekt, Text und Betreff aus einer Mail-Vorlage.
 * Vorlage am Workflow (workflow_mail_timers) und Kopie je Projekt (mail_timers), analog zu den Meilensteinen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_mail_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            // Schritt, in dem das Projekt zum Sendetag noch stehen muss (und in dessen Kasten die Erinnerung erscheint)
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_template_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type', 20);            // milestone | phase_end
            $table->foreignId('reference_milestone_id')->nullable()->constrained('workflow_milestones')->cascadeOnDelete();
            $table->foreignId('reference_step_id')->nullable()->constrained('workflow_steps')->cascadeOnDelete();
            $table->integer('offset_days')->default(0);      // Kalendertage, negativ = davor
            $table->boolean('only_if_in_step')->default(true);
            $table->json('function_group_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_mail_timer_id')->nullable()->constrained('workflow_mail_timers')->nullOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->foreignId('mail_template_id')->constrained()->cascadeOnDelete();
            $table->string('reference_type', 20);            // fixed | milestone | phase_end
            $table->foreignId('reference_milestone_id')->nullable()->constrained('project_milestones')->cascadeOnDelete();
            $table->foreignId('reference_step_id')->nullable()->constrained('workflow_steps')->cascadeOnDelete();
            $table->date('fixed_date')->nullable();
            $table->integer('offset_days')->default(0);
            $table->boolean('only_if_in_step')->default(true);
            $table->json('function_group_ids')->nullable();
            $table->date('send_date')->nullable();           // berechnet, leer solange der Bezugstermin fehlt
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('skipped_at')->nullable();      // Schritt war zum Sendetag schon verlassen
            $table->string('last_error')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id']);
            $table->index(['send_date', 'sent_at', 'skipped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_timers');
        Schema::dropIfExists('workflow_mail_timers');
    }
};
