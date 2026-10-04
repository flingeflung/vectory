<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            // Redaktioneller Stand (Ralf, 2026-10-04): "freigegeben" = Text ist geprüft; rein Anzeige, sperrt nichts.
            $table->boolean('approved')->default(false)->after('visible_role');
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('approved');
        });
    }
};
