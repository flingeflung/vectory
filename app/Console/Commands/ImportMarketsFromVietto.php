<?php

namespace App\Console\Commands;

use App\Models\Market;
use App\Models\Project;
use App\Models\Tenant;
use App\Support\NameSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Märkte/Subsprachen aus Vietto (subsprachen+laender+sprachen,
 * rein lesend) sowie deren Zuordnung zu Projekten (laender_pn_cx). Nur die
 * Kombinationen, die auch im Vietto-Picker auftauchen (blnActive=1,
 * blnViega=1 auf laender) – die übrigen Länder/Sprachen sind dort nie
 * eigenständig nutzbare Optionen. sprachen_cx bleibt außen vor, wird im
 * ganzen Vietto-Code nirgends gelesen (totes Altrelikt).
 */
class ImportMarketsFromVietto extends Command
{
    protected $signature = 'markets:import-from-vietto';

    protected $description = 'Märkte/Subsprachen aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('subsprachen as ss')
            ->join('laender as l', 'l.valID', '=', 'ss.valLandID')
            ->join('sprachen as s', 's.valID', '=', 'ss.valSprachID')
            ->where('l.blnActive', 1)
            ->where('l.blnViega', 1)
            ->orderBy('l.intSort')
            ->orderBy('l.strISO3166')
            ->select('ss.valID', 'ss.blnNoTranslation', 'l.strISO3166', 'l.strLandDe', 'l.strLandShortDe', 's.strSpK', 's.strSprache', 'l.intSort')
            ->get();

        $marketMap = [];
        foreach ($rows as $index => $row) {
            $marketMap[$row->valID] = Market::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                [
                    'country_iso' => $row->strISO3166,
                    'country_name' => NameSanitizer::clean($row->strLandDe),
                    'country_short_name' => NameSanitizer::clean($row->strLandShortDe),
                    'language_code' => $row->strSpK,
                    'language_name' => NameSanitizer::clean($row->strSprache),
                    'no_translation' => (bool) $row->blnNoTranslation,
                    'sort' => $index,
                ]
            )->id;
        }

        $this->ensureManualAdditions($tenant->id);

        $projectIds = Project::query()->where('tenant_id', $tenant->id)->pluck('id', 'source_pn');

        // blnDeleted ist Altlast aus der cosima-Anbindung (siehe
        // incl_cosimaimport.php) und wird von Vietto selbst nirgends zum
        // Filtern der angezeigten Märkte verwendet (siehe
        // get_pndetails_maerkte) – wir übernehmen daher konsequent alles.
        $assignments = DB::connection('vietto')->table('laender_pn_cx')
            ->select('pn', 'valSubspracheID')
            ->distinct()
            ->get();

        $count = 0;
        $skipped = 0;

        foreach ($assignments as $assignment) {
            $projectId = $projectIds->get($assignment->pn);
            $marketId = $marketMap[$assignment->valSubspracheID] ?? null;

            if (! $projectId || ! $marketId) {
                $skipped++;

                continue;
            }

            DB::table('project_market')->updateOrInsert(
                ['project_id' => $projectId, 'market_id' => $marketId],
                ['tenant_id' => $tenant->id, 'updated_at' => now(), 'created_at' => now()]
            );
            $count++;
        }

        $this->info(count($marketMap)." Märkte, {$count} Projekt-Zuordnungen importiert".($skipped ? ", {$skipped} übersprungen (kein passendes Projekt/Markt)." : '.'));

        return self::SUCCESS;
    }

    /**
     * Märkte, die es in Vietto (noch) nicht als eigenständige Subsprache
     * gibt, wir aber der Vollständigkeit halber trotzdem brauchen (z.B. hat
     * Vietto für die Schweiz nur CHde angelegt, obwohl das Land drei
     * Landessprachen hat). Ohne legacy_id, damit der Vietto-Import diese
     * Zeilen nie anfasst; Abgleich stattdessen über Land+Sprache.
     */
    private function ensureManualAdditions(int $tenantId): void
    {
        $chDeSort = Market::query()->where('tenant_id', $tenantId)->where('country_iso', 'CH')->value('sort') ?? 0;

        foreach ([
            ['language_code' => 'FR', 'language_name' => 'Französisch'],
            ['language_code' => 'IT', 'language_name' => 'Italienisch'],
        ] as $index => $language) {
            Market::updateOrCreate(
                ['tenant_id' => $tenantId, 'country_iso' => 'CH', 'language_code' => $language['language_code']],
                [
                    'country_name' => 'Schweiz',
                    'country_short_name' => 'Schweiz',
                    'language_name' => $language['language_name'],
                    'no_translation' => false,
                    'sort' => $chDeSort + $index + 1,
                ]
            );
        }
    }
}
