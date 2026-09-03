<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Bereiche aus Vietto (personen_bereiche, rein lesend).
 */
class ImportDepartmentsFromVietto extends Command
{
    protected $signature = 'departments:import-from-vietto';

    protected $description = 'Bereiche aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('personen_bereiche')->orderBy('intSort')->get();

        $count = 0;
        foreach ($rows as $row) {
            Department::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                ['name' => $row->strBereich, 'sort' => $row->intSort, 'active' => (bool) $row->blnActive]
            );
            $count++;
        }

        $this->info("{$count} Bereiche importiert.");

        return self::SUCCESS;
    }
}
