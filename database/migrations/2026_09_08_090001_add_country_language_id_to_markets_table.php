<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Verknüpfung zur globalen Referenz - damit sich beim Öffnen des
     * Katalogs zuverlässig bestimmen lässt, welche Land+Sprache-Zeilen für
     * diesen Kunden bereits ein Markt sind (statt über country_iso/
     * language_code-Strings abzugleichen).
     */
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('language_id');
        });
    }
};
