<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zuordnung Vorlage -> Attribut ("wird beim Kopieren übernommen"),
     * gleiches Muster wie attribute_project_type. Jedes Feld (fest oder
     * Zusatzfeld) ist inzwischen eine Attribute-Zeile - deckt damit
     * Stammdaten/Ablaufdaten/Typspezifisch einheitlich ab.
     */
    public function up(): void
    {
        Schema::create('copy_template_attribute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copy_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['copy_template_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_template_attribute');
    }
};
