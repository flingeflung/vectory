<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generische Person (Rollenmodell Rolle 4 aus CLAUDE.md), unabhängig von
     * einem Login. Entspricht Viettos personen-Tabelle (nur intTyp 1/2,
     * "automatischer Prozess" 99 lassen wir weg). Ein Vectory-User kann
     * optional auf eine Person verweisen (users.person_id) statt umgekehrt -
     * ein Upgrade auf Login-Rolle bedeutet dann nur einen neuen User-Datensatz.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });

        Schema::dropIfExists('people');
    }
};
