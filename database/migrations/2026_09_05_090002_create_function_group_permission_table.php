<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rechte-Vorlage je Funktionsgruppe - Mitgliedschaft in einer Gruppe
     * gewährt standardmäßig deren Rechte (siehe Person::hasPermission()).
     */
    public function up(): void
    {
        Schema::create('function_group_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['function_group_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('function_group_permission');
    }
};
