<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projektbeteiligte Personen: welche Person(en) sind einem Projekt für
     * welche Funktionsgruppe zugeordnet, entspricht Viettos projpersonen.
     * is_primary = Viettos blnIsPL ("Stern", erster Ansprechpartner dieser
     * Funktionsgruppe im Projekt).
     */
    public function up(): void
    {
        Schema::create('project_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('function_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'function_group_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_people');
    }
};
