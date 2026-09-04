<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verknüpfung für aus Illustrationsaufträgen abgeleitete Aufgaben
     * (Task::syncIllustrationTaskForOrder) - analog project_workflow_step_id
     * für WFS-Aufgaben, nur eben je Illustrationsauftrag statt je WFS.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('graphic_order_id')->nullable()->after('project_workflow_step_id')->constrained()->cascadeOnDelete();
            $table->unique(['graphic_order_id', 'person_id'], 'tasks_graphic_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique('tasks_graphic_order_unique');
            $table->dropConstrainedForeignId('graphic_order_id');
        });
    }
};
