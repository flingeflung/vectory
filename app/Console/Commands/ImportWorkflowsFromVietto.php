<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Workflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Workflow-Vorlagen aus Vietto (workflows, rein lesend) - nur
 * intTyp=2 ("neues" System ab 2018), auf Wunsch keine Übernahme des alten
 * Systems (intTyp=1).
 */
class ImportWorkflowsFromVietto extends Command
{
    protected $signature = 'workflows:import-from-vietto';

    protected $description = 'Workflow-Vorlagen aus Vietto übernehmen (nur neues System)';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $rows = DB::connection('vietto')->table('workflows')->where('intTyp', 2)->orderBy('intSort')->get();

        $ids = [];
        foreach ($rows as $row) {
            $ids[$row->valID] = Workflow::updateOrCreate(
                ['tenant_id' => $tenant->id, 'legacy_id' => $row->valID],
                [
                    'short_name' => $row->strWNameShort,
                    'name' => $row->strWName,
                    'description' => $row->strBeschreibung ?: null,
                    'active' => (bool) $row->blnActive,
                    'sort' => $row->intSort,
                ]
            )->id;
        }

        // Zweiter Durchlauf: Nachfolge-Kette (valCopyUpdateID) auflösen, jetzt
        // wo alle Workflows ihre Vectory-ID haben.
        foreach ($rows as $row) {
            if ($row->valCopyUpdateID > 0 && isset($ids[$row->valCopyUpdateID])) {
                Workflow::where('id', $ids[$row->valID])->update(['superseded_by_id' => $ids[$row->valCopyUpdateID]]);
            }
        }

        $this->info(count($ids).' Workflows importiert.');

        return self::SUCCESS;
    }
}
