<?php

namespace App\Console\Commands;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt Projektbeteiligte Personen aus Vietto (projpersonen, rein
 * lesend).
 */
class ImportProjectPeopleFromVietto extends Command
{
    protected $signature = 'project-people:import-from-vietto';

    protected $description = 'Projektbeteiligte Personen aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $projectIds = Project::query()->where('tenant_id', $tenant->id)->pluck('id', 'source_pn');
        $groupIds = FunctionGroup::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $personIds = Person::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $rows = DB::connection('vietto')->table('projpersonen')->get();

        $count = 0;
        $skipped = 0;
        $now = now();
        $batch = [];

        foreach ($rows as $row) {
            $projectId = $projectIds->get($row->strPN);
            $groupId = $groupIds->get($row->valFktGrpID);
            $personId = $personIds->get((int) $row->valPersonID);

            if (! $projectId || ! $groupId || ! $personId) {
                $skipped++;

                continue;
            }

            $batch[] = [
                'tenant_id' => $tenant->id,
                'project_id' => $projectId,
                'function_group_id' => $groupId,
                'person_id' => $personId,
                'is_primary' => (bool) $row->blnIsPL,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $count++;

            if (count($batch) >= 500) {
                DB::table('project_people')->upsert($batch, ['project_id', 'function_group_id', 'person_id'], ['is_primary', 'updated_at']);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('project_people')->upsert($batch, ['project_id', 'function_group_id', 'person_id'], ['is_primary', 'updated_at']);
        }

        $this->info("{$count} Zuordnungen importiert".($skipped ? ", {$skipped} übersprungen." : '.'));

        return self::SUCCESS;
    }
}
