<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die eigentlichen Vorlage-Punkte (Vietto: checklist_list) - global für
     * alle Projekte, die diese Checkliste nutzen. Der Abhak-Zustand pro
     * Projekt lebt separat in project_checklist_points.
     */
    public function up(): void
    {
        Schema::create('checklist_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_points');
    }
};
