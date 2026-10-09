<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vorgänge von Hand (Ralf, 2026-10-09, Vorbild Vietto): eigenes Ereignisdatum (occurred_on), Hervorhebung und Änderungsvermerk.
     * Automatische Vorgänge lassen occurred_on leer und zählen mit ihrem Erfassungszeitpunkt.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->date('occurred_on')->nullable()->after('message');
            $table->boolean('is_highlighted')->default(false)->after('occurred_on');
            $table->foreignId('edited_by')->nullable()->after('is_highlighted')->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('edited_by');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn(['occurred_on', 'is_highlighted', 'edited_at']);
        });
    }
};
