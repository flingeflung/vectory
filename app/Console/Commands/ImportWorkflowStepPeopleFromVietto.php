<?php

namespace App\Console\Commands;

use App\Models\FunctionGroup;
use App\Models\Person;
use App\Models\ProjectWorkflowStep;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt die Personen-pro-WFS-Zuweisung aus Vietto (workflow_pers_cx2,
 * rein lesend) - für die reine Darstellung im Workflow-Schritte-Tab. Nur
 * lesend, keine Zuweisungs-/Auto-Sync-Logik (folgt später).
 */
class ImportWorkflowStepPeopleFromVietto extends Command
{
    protected $signature = 'workflow-step-people:import-from-vietto';

    protected $description = 'Personen-pro-Workflow-Schritt aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $functionGroupIds = FunctionGroup::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $personIds = Person::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        // workflow_pers_cx2.valWFStepID verweist NICHT auf die WFS-Schablone
        // (workflowsteps2), sondern auf die Projekt-Instanz-Zeile
        // (workflowprogress2.valID) - daher direkt über project_workflow_steps.legacy_id auflösen.
        $projectWorkflowStepIds = ProjectWorkflowStep::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('legacy_id')
            ->pluck('id', 'legacy_id');

        $count = 0;
        $skipped = 0;
        $batch = [];
        $bar = $this->output->createProgressBar(DB::connection('vietto')->table('workflow_pers_cx2')->count());

        DB::connection('vietto')->table('workflow_pers_cx2')->orderBy('valID')->chunk(1000, function ($rows) use (
            &$count, &$skipped, &$batch, $bar, $tenant, $functionGroupIds, $personIds, $projectWorkflowStepIds
        ) {
            foreach ($rows as $row) {
                $projectWorkflowStepId = $projectWorkflowStepIds->get($row->valWFStepID);
                $functionGroupId = $functionGroupIds->get($row->valFktGrpID);
                $personId = $personIds->get($row->valPersonID);

                if (! $projectWorkflowStepId || ! $functionGroupId || ! $personId) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $batch[] = [
                    'tenant_id' => $tenant->id,
                    'project_workflow_step_id' => $projectWorkflowStepId,
                    'function_group_id' => $functionGroupId,
                    'person_id' => $personId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $count++;

                if (count($batch) >= 500) {
                    DB::table('project_workflow_step_people')->upsert(
                        $batch,
                        ['project_workflow_step_id', 'function_group_id', 'person_id'],
                        ['updated_at']
                    );
                    $batch = [];
                }

                $bar->advance();
            }
        });

        if (! empty($batch)) {
            DB::table('project_workflow_step_people')->upsert(
                $batch,
                ['project_workflow_step_id', 'function_group_id', 'person_id'],
                ['updated_at']
            );
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$count} Zuweisungen importiert, {$skipped} übersprungen (kein passendes Projekt/Schritt/Person).");

        return self::SUCCESS;
    }
}
