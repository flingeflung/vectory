<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine Zeile je Artikel+Sprache (Ralf, 2026-09-12: "Mehrsprachigkeit
     * vorbereiten") - anfangs wird nur "de" befüllt, weitere Sprachen (z.B.
     * "en", passend zu den bereits vorhandenen lang/en.json) kommen einfach
     * als zusätzliche Zeile dazu, keine Strukturänderung nötig.
     */
    public function up(): void
    {
        Schema::create('help_article_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_article_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            // Zusätzliche Suchbegriffe über den Titel hinaus (Synonyme,
            // Vietto-Begriffe, ...), z.B. "Kopiervorlage, Als Kopie von".
            $table->string('keywords')->nullable();
            $table->longText('body');
            $table->timestamps();

            $table->unique(['help_article_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_article_translations');
    }
};
