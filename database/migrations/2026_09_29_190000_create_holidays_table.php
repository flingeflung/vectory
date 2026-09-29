<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('date');
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
