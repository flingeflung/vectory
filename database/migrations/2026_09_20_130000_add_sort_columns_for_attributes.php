<?php

use App\Models\Attribute;
use App\Services\AttributeColumnManager;
use Illuminate\Database\Migrations\Migration;

/**
 * Sortierspalten für Zusatzfelder (Ralf, 2026-09-20): Zahlenfelder und hochzählende Textfelder
 * (Kundenversion) bekommen eine zweite generierte Spalte, damit die Übersicht sie numerisch
 * bzw. "V9 vor V10" sortieren kann. Neue Felder erhalten sie automatisch (AttributeColumnManager).
 */
return new class extends Migration
{
    public function up(): void
    {
        $manager = app(AttributeColumnManager::class);

        Attribute::query()->withoutGlobalScope('tenant')->get()->each(fn (Attribute $attribute) => $manager->ensureSortColumn($attribute));
    }

    public function down(): void
    {
        // Die Sortierspalten sind reine Ableitungen und harmlos - kein Zurückrollen nötig.
    }
};
