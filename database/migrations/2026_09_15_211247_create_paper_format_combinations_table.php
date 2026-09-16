<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paper_format_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('input_format_id')->constrained('paper_formats')->restrictOnDelete();
            $table->foreignId('output_format_id')->constrained('paper_formats')->restrictOnDelete();
            $table->unsignedSmallInteger('fold_count')->nullable();
            $table->string('remark', 200)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'input_format_id', 'output_format_id'], 'paper_format_combinations_unique_pair');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_format_combinations');
    }
};
