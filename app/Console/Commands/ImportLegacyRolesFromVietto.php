<?php

namespace App\Console\Commands;

use App\Models\LegacyRole;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Viettos fachliche Rollen aus Vietto (rollen, rein lesend) -
 * reines Referenzfeld, siehe LegacyRole-Model.
 */
class ImportLegacyRolesFromVietto extends Command
{
    protected $signature = 'legacy-roles:import-from-vietto';

    protected $description = 'Legacy-Rollen aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('rollen')->orderBy('intSort')->get();

        $count = 0;
        foreach ($rows as $row) {
            LegacyRole::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                ['name' => $row->strRolle, 'sort' => $row->intSort]
            );
            $count++;
        }

        $this->info("{$count} Legacy-Rollen importiert.");

        return self::SUCCESS;
    }
}
