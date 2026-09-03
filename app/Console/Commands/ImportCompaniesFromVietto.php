<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Firmen aus Vietto (firmen, rein lesend) unverändert - Ralf
 * verfremdet sie bei Bedarf selbst von Hand.
 */
class ImportCompaniesFromVietto extends Command
{
    protected $signature = 'companies:import-from-vietto';

    protected $description = 'Firmen aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('firmen')->orderBy('intSort')->get();

        $count = 0;
        foreach ($rows as $row) {
            Company::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                ['name' => $row->strFirma, 'short_name' => $row->strFirmakurz, 'sort' => $row->intSort]
            );
            $count++;
        }

        $this->info("{$count} Firmen importiert.");

        return self::SUCCESS;
    }
}
