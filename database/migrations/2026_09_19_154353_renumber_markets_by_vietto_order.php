<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ralf, 2026-09-19: Märkte nach Viettos Regel (laender.intSort) sortieren -
     * Deutschland, International, "(ohne Land/Sprache)", danach alphabetisch nach
     * Ländername (deutsche Kollation), bei gleichem Land nach Sprache. Siehe
     * Market::sortedForDisplay(), das hier bewusst dupliziert statt importiert
     * wird (Migrationen bleiben unabhängig vom aktuellen Modellstand). Nur die
     * Spalte sort ändert sich, keine Zuordnungen. Die alte Reihenfolge lässt sich
     * nicht wiederherstellen (down() ist bewusst leer).
     */
    public function up(): void
    {
        $collator = new Collator('de_DE');
        $rank = fn (object $market) => match ($market->country_iso) {
            'DE' => 1,
            'INT' => 2,
            'XXX' => 3,
            default => 4,
        };

        foreach (DB::table('markets')->distinct()->pluck('tenant_id') as $tenantId) {
            $markets = DB::table('markets')->where('tenant_id', $tenantId)->get()
                ->sort(fn (object $a, object $b) => $rank($a) <=> $rank($b)
                    ?: $collator->compare($a->country_name, $b->country_name)
                    ?: $collator->compare($a->language_name, $b->language_name)
                    ?: $a->id <=> $b->id)
                ->values();

            foreach ($markets as $index => $market) {
                if ((int) $market->sort !== $index + 1) {
                    DB::table('markets')->where('id', $market->id)->update(['sort' => $index + 1]);
                }
            }
        }
    }

    public function down(): void
    {
        // Reine Sortierung, alte Reihenfolge nicht rekonstruierbar.
    }
};
