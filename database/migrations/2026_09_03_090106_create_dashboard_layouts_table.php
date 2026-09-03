<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Welche Dashboard-Kacheln ein Benutzer aktiviert hat und in welcher
     * Reihenfolge (config['active_tiles'] = Liste von Kachel-Keys aus dem
     * DashboardTileCatalog). Die "Meldungen"-Kachel ist immer fix oben links
     * und taucht daher NICHT in dieser Liste auf. Analog zum
     * display_filter_sets-Muster, aber schlanker (nur ein Layout je Nutzer,
     * kein Name/Aktivierung mehrerer Sets nötig).
     */
    public function up(): void
    {
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('config');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
    }
};
