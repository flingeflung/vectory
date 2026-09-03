<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entspricht Viettos subsprachen.blnNoTranslation – steuert den "*"
     * hinter dem Markt in der Projektdetail-Anzeige ("keine Übersetzung für
     * diesen Markt").
     */
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->string('country_short_name')->nullable()->after('country_name');
            $table->boolean('no_translation')->default(false)->after('language_name');
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropColumn(['country_short_name', 'no_translation']);
        });
    }
};
