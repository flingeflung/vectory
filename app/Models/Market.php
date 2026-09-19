<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'legacy_id', 'country_id', 'language_id', 'country_iso', 'country_name', 'country_short_name', 'language_code', 'language_name', 'no_translation', 'sort'])]
class Market extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'no_translation' => 'boolean',
        ];
    }

    /**
     * Ralf, 2026-09-19: Sortierung nach Viettos laender.intSort - erst
     * Deutschland, dann International, dann "(ohne Land/Sprache)", danach alle
     * übrigen Märkte des Kunden alphabetisch nach Ländername (deutsche
     * Kollation: Österreich bei Ö, nicht hinter Z), bei gleichem Land nach
     * Sprache. In Vectory sind alle Markt-Zeilen eines Kunden die für ihn
     * relevanten; Viettos "alles Weitere" (nicht aktivierte Länder) steht nur in
     * der Marktverwaltung zur Auswahl. Vorgesehen (noch nicht gebaut): eine
     * Hauptsprache je Kunde, deren Märkte vor DE stehen.
     *
     * Alle Listen im Tool sortieren nach der Spalte sort - sie wird deshalb bei
     * jeder Änderung des Marktbestands eines Kunden neu durchnummeriert, statt
     * an jeder Stelle einzeln zu sortieren.
     *
     * @param  \Illuminate\Support\Collection<int, static>  $markets
     * @return \Illuminate\Support\Collection<int, static> sortiert
     */
    public static function sortedForDisplay(\Illuminate\Support\Collection $markets): \Illuminate\Support\Collection
    {
        $collator = new \Collator('de_DE');
        $rank = fn (self $market) => match ($market->country_iso) {
            'DE' => 1,
            'INT' => 2,
            'XXX' => 3,
            default => 4,
        };

        return $markets->sort(function (self $a, self $b) use ($collator, $rank) {
            return $rank($a) <=> $rank($b)
                ?: $collator->compare($a->country_name, $b->country_name)
                ?: $collator->compare($a->language_name, $b->language_name)
                ?: $a->id <=> $b->id;
        })->values();
    }

    /**
     * Nummeriert die Märkte eines Kunden nach sortedForDisplay() lückenlos
     * durch (1..n). Roh über den Mandanten-Scope hinweg, weil der Kunde nicht
     * zwingend der gerade aktive ist (z.B. beim Klonen eines Kunden).
     */
    public static function renumberForTenant(int $tenantId): void
    {
        $markets = static::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->get();

        static::sortedForDisplay($markets)->each(function (self $market, int $index) {
            if ((int) $market->sort !== $index + 1) {
                static::query()->withoutGlobalScope('tenant')->whereKey($market->id)->update(['sort' => $index + 1]);
            }
        });
    }

    public function label(): string
    {
        return "{$this->country_iso} ".strtolower($this->language_code)." – {$this->country_name}; {$this->language_name}";
    }

    /**
     * Kompaktes Format für die Projektdetails, entspricht Viettos
     * get_pndetails_maerkte(): fetter ISO+Sprachcode, "*" bei
     * no_translation, kurzer Ländername.
     */
    public function shortLabel(): string
    {
        $star = $this->no_translation ? '*' : '';

        return "{$this->country_iso}".strtolower($this->language_code)."{$star} {$this->country_short_name}";
    }

    /**
     * Nicht jede Land/Sprache-Kombination hat ein Icon (schon in Vietto
     * selbst fehlen z.B. für AE/JP welche) – dann greift der Text-Fallback.
     */
    public function iconUrl(): ?string
    {
        $path = 'images/markticons/'.strtolower($this->country_iso).'.png';

        return file_exists(public_path($path)) ? asset($path) : null;
    }
}
