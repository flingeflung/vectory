<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status-Katalog fest verdrahtet statt Tenant-Tabelle (siehe
     * App\Enums\GraphicOrderStatus) - is_open/is_discarded steuern echte
     * Logik, kein freier Tenant-Einstellwert (Ralf-Entscheidung, nachdem
     * ein neuer Mandant ohne Status-Katalog beim Speichern eines
     * Illustrationsauftrags scheiterte). Bestehende graphic_orders-Werte
     * in graphic_order_status_id (1-8) bleiben unverändert gültig -
     * identisch zu Viettos valID/legacy_id.
     */
    public function up(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->dropForeign(['graphic_order_status_id']);
        });

        Schema::dropIfExists('graphic_order_statuses');
    }

    public function down(): void
    {
        Schema::create('graphic_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('legacy_id')->nullable();
            $table->string('name');
            $table->integer('sort')->default(0);
            $table->boolean('is_open')->default(false);
            $table->boolean('is_discarded')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'legacy_id']);
        });

        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->foreign('graphic_order_status_id')->references('id')->on('graphic_order_statuses')->cascadeOnDelete();
        });
    }
};
