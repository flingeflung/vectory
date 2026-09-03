<?php

namespace App\Console\Commands;

use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use App\Models\Tenant;
use App\Support\NameSanitizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Projekttyp (Haupt) + Projektart (Unterart) aus Vietto
 * (projartmain/projartsub, rein lesend) in die eigenen, mandantenscoped
 * Tabellen project_type_mains/project_type_subs. Nur aktive Unterarten
 * (blnActive=1).
 */
class ImportProjectTypesFromVietto extends Command
{
    protected $signature = 'project-types:import-from-vietto';

    protected $description = 'Projekttyp/-art aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $mains = DB::connection('vietto')->table('projartmain')->orderBy('intSort')->get();
        $subs = DB::connection('vietto')->table('projartsub')->where('blnActive', 1)->orderBy('intSort')->get();

        $mainCount = 0;
        $subCount = 0;

        foreach ($mains as $main) {
            $mainType = ProjectTypeMain::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $main->valID],
                ['name' => NameSanitizer::clean($main->strProjArt), 'sort' => $main->intSort]
            );
            $mainCount++;

            foreach ($subs->where('valIDMain', $main->valID) as $sub) {
                ProjectTypeSub::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'legacy_id' => $sub->valID],
                    [
                        'project_type_main_id' => $mainType->id,
                        'name' => NameSanitizer::clean($sub->strProjArt),
                        'symbol' => $sub->strSymbol ?: null,
                        'sort' => $sub->intSort,
                    ]
                );
                $subCount++;
            }
        }

        $this->info("{$mainCount} Hauptarten, {$subCount} Unterarten importiert.");

        return self::SUCCESS;
    }
}
