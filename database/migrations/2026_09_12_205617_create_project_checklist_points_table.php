<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abhak-Zustand pro Projekt+Punkt (Vietto: checklist_items). Anders als
     * in Vietto ein echter Unique-Index auf (project_id, checklist_point_id)
     * - dort waren Duplikate durch Fehlen dessen theoretisch möglich.
     */
    public function up(): void
    {
        Schema::create('project_checklist_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_point_id')->constrained()->cascadeOnDelete();
            $table->boolean('done')->default(false);
            $table->foreignId('done_by_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'checklist_point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_checklist_points');
    }
};
