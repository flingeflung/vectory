<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zuordnung Attribut -> Projektart (Analogon zu Viettos
     * attribute_projekttyp_cx). Referenziert vorerst den rohen
     * project_type_sub-Code aus projects, da Projektarten selbst noch keine
     * eigene (mandantenscoped) Tabelle haben.
     */
    public function up(): void
    {
        Schema::create('attribute_project_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('project_type_sub');
            $table->timestamps();

            $table->unique(['attribute_id', 'project_type_sub']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_project_type');
    }
};
