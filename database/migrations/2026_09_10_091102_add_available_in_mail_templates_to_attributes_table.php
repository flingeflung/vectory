<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-10: bei der ohnehin noch ausstehenden Definition
     * zahlreicher weiterer Projektattribute soll pro Attribut gleich
     * mitentschieden werden, ob es als Platzhalter in Mail-Vorlagen zur
     * Verfügung steht - keine separate, zweite Feldliste pflegen.
     */
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('available_in_mail_templates')->default(false)->after('data_type');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('available_in_mail_templates');
        });
    }
};
