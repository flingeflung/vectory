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

    /**
     * Umbenennungen ggü. Vietto (legacy_id => Vectory-Name) - z.B.
     * "Grafikerstellung" heißt bei uns einheitlich "Illustration".
     */
    private const NAME_OVERRIDES = [
        5 => 'Illustration',
    ];

    /**
     * Kurzform-Umbenennungen (legacy_id => Vectory-Kurzform) - Viettos
     * strFunktionsgruppeShort für Gruppe 5 ist weiterhin "Grafik".
     */
    private const SHORT_NAME_OVERRIDES = [
        5 => 'Illu',
    ];

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
                    'name' => self::NAME_OVERRIDES[$row->valID] ?? $row->strFunktionsgruppe,
                    'short_name' => self::SHORT_NAME_OVERRIDES[$row->valID] ?? $row->strFunktionsgruppeShort,
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
