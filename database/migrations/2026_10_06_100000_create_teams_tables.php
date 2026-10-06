<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams (Ralf, 2026-10-06): benannte Personengruppen je Organisation, unabhängig von den Funktionsgruppen.
     * Eine Person kann in mehreren Teams sein; Mitglieder können auch ausgeliehene Personen anderer Organisationen sein.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('team_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            // member | lead (Teamleiter) | deputy (stv. Teamleiter); mehrere Leiter/Stellvertreter je Team möglich
            $table->string('role', 10)->default('member');
            $table->timestamps();

            $table->unique(['team_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_person');
        Schema::dropIfExists('teams');
    }
};
