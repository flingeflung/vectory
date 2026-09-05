<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Individuelle Ausnahmen on top der Funktionsgruppen-Vorlage: granted=1
     * gewährt ein Recht auch ohne (oder trotz fremder) Gruppenzugehörigkeit,
     * granted=0 entzieht ein Recht, das die Gruppe eigentlich gewähren
     * würde. Eine Zeile pro Person+Recht überschreibt die Gruppen-Vorlage
     * komplett (siehe Person::hasPermission()).
     */
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('person_permission');
    }
};
