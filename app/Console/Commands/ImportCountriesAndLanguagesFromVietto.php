<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Language;
use App\Support\NameSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt die vollständigen Länder-/Sprachen-Referenzlisten aus Vietto
 * (laender, sprachen) - anders als ImportMarketsFromVietto NICHT auf
 * blnViega=1 gefiltert, das war die Viega-spezifische Kuration. Ralf: "das
 * ist ja weltweit nach ISO festgelegt... die würde ich hier schon mal
 * komplett aus Vietto übernehmen" - jeder Kunde wählt später selbst per
 * Dropdown aus, welche davon für ihn als Markt eine Rolle spielen (siehe
 * Market-Modell/MarketController), statt sie einzeln abzutippen.
 *
 * Global (keine tenant_id) - einmalig für die ganze Installation, nicht
 * pro Kunde wiederholt.
 */
class ImportCountriesAndLanguagesFromVietto extends Command
{
    protected $signature = 'countries-languages:import-from-vietto';

    protected $description = 'Länder- und Sprachen-Referenzlisten aus Vietto übernehmen (global, einmalig)';

    public function handle(): int
    {
        $countries = DB::connection('vietto')->table('laender')
            ->where('blnActive', 1)
            ->orderBy('intSort')
            ->orderBy('strISO3166')
            ->select('strISO3166', 'strLandDe', 'strLandShortDe', 'intSort')
            ->get();

        foreach ($countries as $country) {
            Country::query()->updateOrCreate(
                ['name' => NameSanitizer::clean($country->strLandDe)],
                [
                    'iso' => $country->strISO3166 ?: null,
                    'short_name' => NameSanitizer::clean($country->strLandShortDe),
                ]
            );
        }

        $languages = DB::connection('vietto')->table('sprachen')
            ->orderBy('intSort')
            ->select('strSpK', 'strSprache', 'intSort')
            ->get();

        foreach ($languages as $index => $language) {
            Language::query()->updateOrCreate(
                ['name' => NameSanitizer::clean($language->strSprache)],
                [
                    'code' => $language->strSpK ?: null,
                    'sort' => $index,
                ]
            );
        }

        $this->info(count($countries).' Länder, '.count($languages).' Sprachen übernommen.');

        return self::SUCCESS;
    }
}
