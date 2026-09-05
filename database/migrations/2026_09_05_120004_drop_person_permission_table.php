<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Individuelle Rechte-Ausnahmen je Person entfallen bewusst - führten zu
     * nicht mehr nachvollziehbarem "Permission Sprawl" (verstreute, nirgends
     * dokumentierte Einzel-Abweichungen). Jede Person hat jetzt genau ein
     * PermissionTemplate, das ihre Rechte vollständig und nachvollziehbar
     * bestimmt.
     */
    public function up(): void
    {
        Schema::dropIfExists('person_permission');
    }

    public function down(): void
    {
        Schema::create('person_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('granted');
            $table->timestamps();

            $table->unique(['person_id', 'permission_id']);
        });
    }
};
