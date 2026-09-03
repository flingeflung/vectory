<?php

namespace App\Console\Commands;

use App\Models\Market;
use App\Models\MarketSet;
use App\Models\Tenant;
use App\Support\NameSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Standard-Märkte-Sets aus Vietto (laendersets, rein lesend).
 * Vietto speichert dort nur Länder (strLandIDs) und expandiert erst beim
 * Anwenden auf alle Subsprachen dieses Landes (siehe
 * ajax_set_standardmaerkte.php) – wir machen dieselbe Expansion hier beim
 * Import, damit market_set_market direkt auf unsere Subsprachen-genauen
 * markets zeigt.
 */
class ImportMarketSetsFromVietto extends Command
{
    protected $signature = 'market-sets:import-from-vietto';

    protected $description = 'Standard-Märkte-Sets aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $marketsByCountryLegacyId = DB::connection('vietto')->table('subsprachen')
            ->select('valID', 'valLandID')
            ->get()
            ->groupBy('valLandID');

        $marketByLegacyId = Market::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $sets = DB::connection('vietto')->table('laendersets')->where('blnActive', 1)->orderBy('intSort')->get();

        $setCount = 0;
        $assignmentCount = 0;

        foreach ($sets as $set) {
            $marketSet = MarketSet::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $set->valID],
                ['name' => NameSanitizer::clean($set->strSettitel), 'sort' => $set->intSort]
            );
            $setCount++;

            $marketIds = collect(explode(';', $set->strLandIDs))
                ->filter(fn ($countryId) => $countryId !== '')
                ->flatMap(fn ($countryId) => $marketsByCountryLegacyId->get((int) $countryId, collect())->pluck('valID'))
                ->map(fn ($subspracheId) => $marketByLegacyId->get($subspracheId))
                ->filter()
                ->unique()
                ->values();

            $marketSet->markets()->sync($marketIds->mapWithKeys(fn ($marketId) => [
                $marketId => ['tenant_id' => $tenant->id],
            ])->all());
            $assignmentCount += $marketIds->count();
        }

        $this->info("{$setCount} Standard-Märkte-Sets, {$assignmentCount} Markt-Zuordnungen importiert.");

        return self::SUCCESS;
    }
}
