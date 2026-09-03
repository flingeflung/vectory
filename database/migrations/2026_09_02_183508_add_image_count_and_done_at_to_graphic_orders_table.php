<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Für die "Illustration"-Spalte der Übersicht (X Grafikaufträge/Y erledigt/Z
     * Grafiken gesamt), entspricht Viettos intAnzBilder/valDoneUserID+dtgDoneDate.
     * "erledigt" ist NICHT gleich Status "Fertig und abgelegt" - Vietto prüft
     * separat, ob ein Erledigt-User gesetzt wurde (kann auch bei anderem
     * Status noch gesetzt sein). done_at ist daher nur gefüllt, wenn Vietto
     * tatsächlich valDoneUserID > 0 hatte.
     */
    public function up(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('image_count')->default(0)->after('graphic_order_status_id');
            $table->timestamp('done_at')->nullable()->after('image_count');
        });
    }

    public function down(): void
    {
        Schema::table('graphic_orders', function (Blueprint $table) {
            $table->dropColumn(['image_count', 'done_at']);
        });
    }
};
