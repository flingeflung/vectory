<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt den globalen Land↔Sprache-Katalog aus Viettos subsprachen
 * (welche Sprachen sind in welchem Land relevant) - die rechte Tabelle der
 * Märkte-Seite (siehe MarketController). Anders als
 * ImportMarketsFromVietto NICHT auf laender.blnViega gefiltert, das war
 * die Viega-spezifische Kuration; hier zählt nur, dass das Land aktiv ist
 * (siehe ImportCountriesAndLanguagesFromVietto, gleicher Filter).
 */
class ImportCountryLanguagesFromVietto extends Command
{
    protected $signature = 'countries-languages:import-pairs-from-vietto';

    protected $description = 'Land↔Sprache-Katalog aus Vietto übernehmen (global, einmalig)';

    public function handle(): int
    {
        $countryIds = Country::query()->pluck('id', 'iso');
        $languageIds = Language::query()->pluck('id', 'code');

        $rows = DB::connection('vietto')->table('subsprachen as ss')
            ->join('laender as l', 'l.valID', '=', 'ss.valLandID')
            ->join('sprachen as s', 's.valID', '=', 'ss.valSprachID')
            ->where('l.blnActive', 1)
            ->select('l.strISO3166', 's.strSpK')
            ->distinct()
            ->get();

        $count = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $countryId = $countryIds->get($row->strISO3166);
            $languageId = $languageIds->get($row->strSpK);

            if (! $countryId || ! $languageId) {
                $skipped++;

                continue;
            }

            DB::table('country_languages')->updateOrInsert(
                ['country_id' => $countryId, 'language_id' => $languageId],
                ['updated_at' => now(), 'created_at' => now()]
            );
            $count++;
        }

        $this->info("{$count} Land↔Sprache-Paare übernommen".($skipped ? ", {$skipped} übersprungen (Land/Sprache nicht gefunden)." : '.'));

        return self::SUCCESS;
    }
}
