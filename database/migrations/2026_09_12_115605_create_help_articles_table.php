<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hilfesystem (Ralf, 2026-09-12): "?" oben in der Topbar, Kontexthilfe
     * pro Seite + Stichwortsuche. Bewusst OHNE tenant_id - der Inhalt
     * erklärt Vectory selbst, nicht Kundenkonfiguration, gilt also
     * mandantenübergreifend gleich.
     */
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // Laravel-Routennamen, zu denen dieser Artikel automatisch als
            // Kontexthilfe angezeigt wird (z.B. "admin.kunden") - ein Artikel
            // kann auch OHNE Zuordnung existieren (nur über die Suche
            // erreichbar).
            $table->json('route_names')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
