<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welche Märkte (Subsprachen) zu einem Standard-Märkte-Set gehören.
     * Bewusst auf Subsprachen- statt Länderebene wie Viettos laendersets
     * (dort nur strLandIDs) – konsistent mit der Markt-Zuweisung am Projekt.
     */
    public function up(): void
    {
        Schema::create('market_set_market', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['market_set_id', 'market_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_set_market');
    }
};
