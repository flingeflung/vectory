<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Market;
use App\Models\MarketSet;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Verwaltung der Ländergruppen (Vietto: laendersets) und - darüber - der
 * Märkte (Land+Sprache-Kombination, Vietto-Vorbild: subsprachen) selbst.
 *
 * Ralf: rechts steht immer der komplette, globale Land+Sprache-Katalog
 * (Country::languages(), siehe ImportCountryLanguagesFromVietto) nur zur
 * Ansicht. Sobald links eine Ländergruppe ausgewählt ist, bekommt jede
 * Zeile einen Haken; Anhaken+Speichern legt bei Bedarf automatisch den
 * passenden Market-Datensatz für diesen Kunden an und ordnet ihn der
 * Gruppe zu - kein separater "Markt anlegen"-Schritt mehr.
 */
class MarketController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();

        $sets = MarketSet::query()->where('tenant_id', $tenantId)->orderBy('name')->get();
        $countries = Country::with('languages')->orderBy('name')->get();

        $selectedSet = null;
        $checkedPairs = collect();

        if ($request->filled('gruppe')) {
            $selectedSet = $sets->firstWhere('id', (int) $request->query('gruppe'));
            if ($selectedSet) {
                $checkedPairs = $selectedSet->markets()
                    ->whereNotNull('markets.country_id')
                    ->whereNotNull('markets.language_id')
                    ->get(['markets.country_id', 'markets.language_id'])
                    ->map(fn ($market) => "{$market->country_id}-{$market->language_id}");

                // Angehakte oben, danach der Rest - sowohl die Sprachen
                // innerhalb einer Länderzeile als auch die Länderzeilen
                // selbst (Land zählt als "angehakt", sobald mindestens
                // eine seiner Sprachen dabei ist).
                $countries = $countries
                    ->map(function ($country) use ($checkedPairs) {
                        $country->setRelation(
                            'languages',
                            $country->languages->sortByDesc(fn ($language) => $checkedPairs->contains("{$country->id}-{$language->id}"))->values()
                        );

                        return $country;
                    })
                    ->sortByDesc(fn ($country) => $country->languages->contains(fn ($language) => $checkedPairs->contains("{$country->id}-{$language->id}")))
                    ->values();
            }
        }

        $existingMarkets = Market::query()->where('tenant_id', $tenantId)
            ->whereNotNull('country_id')->whereNotNull('language_id')
            ->get(['id', 'country_id', 'language_id', 'no_translation'])
            ->keyBy(fn ($market) => "{$market->country_id}-{$market->language_id}");

        return view('admin.maerkte.index', [
            'sets' => $sets,
            'countries' => $countries,
            'selectedSet' => $selectedSet,
            'checkedPairs' => $checkedPairs,
            'existingMarkets' => $existingMarkets,
        ]);
    }

    /**
     * Ralf, 2026-09-11: "Es wird keine Übersetzung für diesen Markt
     * durchgeführt" (Anzeige in den Projektdetails, Markt-Feld) war bisher
     * nirgendwo konfigurierbar - kam nur aus dem ursprünglichen Vietto-
     * Import mit. Sofort-Toggle wie beim Projektart-Zuordnungs-Raster
     * (AttributeController::toggleProjectType) statt eigenem Formular, da
     * es ein einzelnes Flag an einem schon bestehenden Datensatz ist.
     */
    public function toggleNoTranslation(Market $market): RedirectResponse
    {
        abort_unless($market->tenant_id === CurrentTenant::id(), 404);

        $market->update(['no_translation' => ! $market->no_translation]);

        return redirect()->back();
    }

    public function setsStore(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $nextSort = 1 + (int) MarketSet::query()->where('tenant_id', $tenantId)->max('sort');
        $set = MarketSet::query()->create(['tenant_id' => $tenantId, 'name' => $name, 'sort' => $nextSort]);

        return redirect()->route('admin.maerkte', ['gruppe' => $set->id])->with('status', 'maerkte-updated');
    }

    public function setsUpdate(Request $request, MarketSet $set): RedirectResponse
    {
        abort_unless($set->tenant_id === CurrentTenant::id(), 404);

        $name = trim((string) $request->string('name'));
        abort_if($name === '', 422);

        $set->update(['name' => $name]);

        return redirect()->route('admin.maerkte', ['gruppe' => $set->id])->with('status', 'maerkte-updated');
    }

    /**
     * Löschen einer Ländergruppe ist unproblematisch (reines Auswahl-
     * Preset, keine echte Projektzuordnung hängt daran) - die zugehörigen
     * Market-Datensätze bleiben für den Kunden erhalten, nur die
     * Gruppenzuordnung entfällt.
     */
    public function setsDestroy(MarketSet $set): RedirectResponse
    {
        abort_unless($set->tenant_id === CurrentTenant::id(), 404);

        $set->delete();

        return redirect()->route('admin.maerkte');
    }

    /**
     * Speichert die angehakten Land+Sprache-Zeilen für eine Ländergruppe.
     * Für jede angehakte Kombination wird der Market-Datensatz des Kunden
     * gefunden oder neu angelegt (Stammdaten aus Country/Language kopiert,
     * danach eine eigenständige, pro Kunde editierbare Kopie); die Gruppe
     * wird anschließend exakt auf diese Menge synchronisiert. Entfernte
     * Haken löschen den Market NICHT - er bleibt als eigenständiger
     * Datensatz bestehen (könnte in einer anderen Gruppe stecken).
     */
    public function setsMembersUpdate(Request $request, MarketSet $set): RedirectResponse
    {
        abort_unless($set->tenant_id === CurrentTenant::id(), 404);

        $tenantId = $set->tenant_id;
        $pairs = collect($request->array('pairs'))
            ->map(function ($pair) {
                [$countryId, $languageId] = array_pad(explode('-', (string) $pair, 2), 2, null);

                return ['country_id' => (int) $countryId, 'language_id' => (int) $languageId];
            })
            ->filter(fn ($pair) => $pair['country_id'] && $pair['language_id']);

        $countries = Country::with('languages')->whereIn('id', $pairs->pluck('country_id')->unique())->get()->keyBy('id');

        $nextSort = 1 + (int) Market::query()->where('tenant_id', $tenantId)->max('sort');
        $marketIds = collect();

        foreach ($pairs as $pair) {
            $country = $countries->get($pair['country_id']);
            $language = $country?->languages->firstWhere('id', $pair['language_id']);

            if (! $country || ! $language) {
                continue;
            }

            $market = Market::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'country_id' => $country->id, 'language_id' => $language->id],
                [
                    'country_iso' => $country->iso,
                    'country_name' => $country->name,
                    'country_short_name' => $country->short_name,
                    'language_code' => $language->code,
                    'language_name' => $language->name,
                    'no_translation' => false,
                    'sort' => $nextSort++,
                ]
            );

            $marketIds->push($market->id);
        }

        // market_set_market.tenant_id ist NOT NULL ohne Default - sync()
        // füllt Pivot-Spalten sonst nicht automatisch, deshalb explizit je
        // Zeile mitgeben (gleiches Muster wie function_group_member).
        $set->markets()->sync($marketIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenantId]]));

        return redirect()->route('admin.maerkte', ['gruppe' => $set->id])->with('status', 'maerkte-updated');
    }
}
