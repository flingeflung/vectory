<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt die aktuelle Workflow-Zuweisung + den Fortschritt je Projekt aus
 * Vietto (projekte.valWorkflowID + workflowprogress2, rein lesend). Nur
 * Projekte, deren Workflow zum neuen System gehört (intTyp=2) - beim alten
 * System bleibt das Projekt ohne Workflow (wie besprochen keine Übernahme).
 */
class ImportProjectWorkflowsFromVietto extends Command
{
    protected $signature = 'project-workflows:import-from-vietto';

    protected $description = 'Workflow-Zuweisung + Fortschritt je Projekt aus Vietto übernehmen';

    public function handle(): int
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->error('Kein Mandant vorhanden.');

            return self::FAILURE;
        }

        $workflowIds = Workflow::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $workflowStepIds = WorkflowStep::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');
        $personIds = Person::query()->where('tenant_id', $tenant->id)->pluck('id', 'legacy_id');

        $sanitizeDateTime = function (?string $value): ?string {
            if (! $value || ! preg_match('/^(\d{4})-\d{2}-\d{2}/', $value, $matches)) {
                return null;
            }

            return ((int) $matches[1]) < 1000 ? null : $value;
        };

        // Schritt 1: Workflow-Zuweisung je Projekt setzen (nur intTyp=2).
        $vectoryProjectIds = Project::query()->where('tenant_id', $tenant->id)->pluck('id', 'source_pn');

        $projects = DB::connection('vietto')->table('projekte as p')
            ->join('workflows as w', 'w.valID', '=', 'p.valWorkflowID')
            ->where('w.intTyp', 2)
            ->select('p.pn', 'p.valWorkflowID')
            ->get();

        $assignedCount = 0;
        $projectIdByPn = [];
        $updates = [];
        foreach ($projects as $row) {
            $workflowId = $workflowIds->get($row->valWorkflowID);
            $projectId = $vectoryProjectIds->get($row->pn);

            if (! $workflowId || ! $projectId) {
                continue;
            }

            $updates[$workflowId][] = $projectId;
            $projectIdByPn[$row->pn] = $projectId;
            $assignedCount++;
        }

        foreach ($updates as $workflowId => $projectIdsForWorkflow) {
            Project::query()->whereIn('id', $projectIdsForWorkflow)->update(['workflow_id' => $workflowId]);
        }

        $this->info("{$assignedCount} Projekte einem Workflow zugewiesen.");

        // Schritt 2: Fortschritt (Schritt-Instanzen) für genau diese Projekte
        // übernehmen, batchweise wegen der Datenmenge.
        $count = 0;
        $skipped = 0;
        $batch = [];
        $now = now();

        DB::connection('vietto')->table('workflowprogress2')
            ->whereIn('strPN', array_keys($projectIdByPn))
            ->orderBy('strPN')
            ->orderBy('intSort')
            ->chunk(1000, function ($rows) use (&$batch, &$count, &$skipped, $projectIdByPn, $workflowStepIds, $personIds, $sanitizeDateTime, $tenant, $now) {
                foreach ($rows as $row) {
                    $projectId = $projectIdByPn[$row->strPN] ?? null;
                    $workflowStepId = $workflowStepIds->get($row->valWFStepID);

                    if (! $projectId || ! $workflowStepId) {
                        $skipped++;

                        continue;
                    }

                    $batch[] = [
                        'tenant_id' => $tenant->id,
                        'project_id' => $projectId,
                        'workflow_step_id' => $workflowStepId,
                        'legacy_id' => $row->valID,
                        'sort' => $row->intSort,
                        'is_current' => (bool) $row->blnIsCurrentStep,
                        'started_at' => $sanitizeDateTime($row->dtgStart),
                        'due_date' => $sanitizeDateTime($row->dtgTermin),
                        'milestone_done_at' => $sanitizeDateTime($row->dtgMSDone),
                        'completed_at' => $sanitizeDateTime($row->dtgDateBeendet),
                        'completed_by_person_id' => $personIds->get($row->valBeendetUserID),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count++;

                    if (count($batch) >= 500) {
                        DB::table('project_workflow_steps')->upsert(
                            $batch,
                            ['project_id', 'workflow_step_id'],
                            ['legacy_id', 'sort', 'is_current', 'started_at', 'due_date', 'milestone_done_at', 'completed_at', 'completed_by_person_id', 'updated_at']
                        );
                        $batch = [];
                    }
                }
            });

        if (! empty($batch)) {
            DB::table('project_workflow_steps')->upsert(
                $batch,
                ['project_id', 'workflow_step_id'],
                ['legacy_id', 'sort', 'is_current', 'started_at', 'due_date', 'milestone_done_at', 'completed_at', 'completed_by_person_id', 'updated_at']
            );
        }

        $this->info("{$count} Schritt-Instanzen importiert".($skipped ? ", {$skipped} übersprungen." : '.'));

        return self::SUCCESS;
    }
}
