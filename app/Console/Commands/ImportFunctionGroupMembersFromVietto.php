<?php

namespace App\Console\Commands;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt den Personen-Pool je Funktionsgruppe aus Vietto
 * (funktionsgruppenmembers, rein lesend).
 */
class ImportFunctionGroupMembersFromVietto extends Command
{
    protected $signature = 'function-group-members:import-from-vietto';

    protected $description = 'Funktionsgruppen-Mitglieder aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $groupIds = FunctionGroup::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $personIds = Person::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $rows = DB::connection('vietto')->table('funktionsgruppenmembers')->get();

        $count = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $groupId = $groupIds->get($row->valFunktionsgruppenID);
            $personId = $personIds->get($row->valPersonID);

            if (! $groupId || ! $personId) {
                $skipped++;

                continue;
            }

            DB::table('function_group_member')->updateOrInsert(
                ['function_group_id' => $groupId, 'person_id' => $personId],
                ['tenant_id' => $tenant->id, 'updated_at' => now(), 'created_at' => now()]
            );
            $count++;
        }

        $this->info("{$count} Zuordnungen importiert".($skipped ? ", {$skipped} übersprungen." : '.'));

        return self::SUCCESS;
    }
}
