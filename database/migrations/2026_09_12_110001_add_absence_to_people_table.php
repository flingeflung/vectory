<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abwesenheits-Markierung (Ralf, 2026-09-12, Backlog-Punkt "Abwesenheits-
     * Markierung"): jede Person kann sich selbst als abwesend markieren
     * (eigene Einstellungen, siehe SettingsController), mit optionalem
     * "bis wann"-Datum. Ist das Datum gesetzt und liegt in der
     * Vergangenheit, gilt die Person NICHT mehr als abwesend, auch wenn
     * `is_absent` noch angehakt ist - kein manuelles Aufräumen nötig, wenn
     * jemand vergisst, das Häkchen nach der Rückkehr zu entfernen (siehe
     * Person::isCurrentlyAbsent()).
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->boolean('is_absent')->default(false)->after('active');
            $table->date('absent_until')->nullable()->after('is_absent');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['is_absent', 'absent_until']);
        });
    }
};
