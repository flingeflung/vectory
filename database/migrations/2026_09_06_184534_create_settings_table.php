<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mandantenbezogene Grundeinstellungen (Konfig-Admin-Seite), z.B.
     * Dateipfade wie bisher hart in Viettos incl_functions.php codiert
     * (siehe $pfad_vietto_lokal, $dir_viettodaten dort). Bekannte Keys +
     * Labels/Beschreibungen sind in Setting::DEFINITIONS im Code gepflegt,
     * nicht per UI frei anlegbar (analog zum Rechte-Katalog).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('value', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
