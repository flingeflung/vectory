<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_person_base_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planning_base_load_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('calculation_type', 16);
            $table->decimal('value', 10, 2);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->timestamps();

            $table->index(['tenant_id', 'person_id', 'year']);
            $table->unique(['person_id', 'planning_base_load_id'], 'person_base_load_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_person_base_loads');
    }
};
