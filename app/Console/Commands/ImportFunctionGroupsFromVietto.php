<?php

namespace App\Console\Commands;

use App\Models\FunctionGroup;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Funktionsgruppen aus Vietto (funktionsgruppen, rein lesend).
 */
class ImportFunctionGroupsFromVietto extends Command
{
    protected $signature = 'function-groups:import-from-vietto';

    protected $description = 'Funktionsgruppen aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('funktionsgruppen')->orderBy('intSort')->get();

        $count = 0;
        foreach ($rows as $row) {
            FunctionGroup::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                [
                    'name' => $row->strFunktionsgruppe,
                    'short_name' => $row->strFunktionsgruppeShort,
                    'sort' => $row->intSort,
                    'active' => (bool) $row->blnActive,
                ]
            );
            $count++;
        }

        $this->info("{$count} Funktionsgruppen importiert.");

        return self::SUCCESS;
    }
}
