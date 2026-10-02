<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('critical_project_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('finding_key');
            $table->string('rule_code');
            $table->timestamps();

            $table->unique(['project_id', 'finding_key']);
        });

        Schema::create('critical_project_finding_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('critical_project_finding_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->date('hidden_until')->nullable();
            $table->timestamps();

            $table->unique(['critical_project_finding_id', 'user_id'], 'critical_finding_user_unique');
            $table->foreign('critical_project_finding_id', 'critical_finding_occurrence_fk')
                ->references('id')->on('critical_project_findings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_project_finding_states');
        Schema::dropIfExists('critical_project_findings');
    }
};
