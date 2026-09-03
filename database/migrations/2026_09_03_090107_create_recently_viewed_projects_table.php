<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Für die Dashboard-Kachel "Zuletzt geöffnete Projekte" - ein Eintrag je
     * (Benutzer, Projekt), viewed_at wird bei jedem erneuten Öffnen
     * aktualisiert (kein Duplikate-Anhäufen). Anzeige/Aufräumen auf die
     * letzten N Einträge passiert in der Anwendungslogik (Limit beim Lesen +
     * Trimmen überzähliger Zeilen beim Schreiben).
     */
    public function up(): void
    {
        Schema::create('recently_viewed_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');

            $table->unique(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed_projects');
    }
};
