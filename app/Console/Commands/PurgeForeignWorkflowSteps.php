<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Räumt Altlasten auf (Ralf, 2026-10-09): Projekte, die früher den Workflow gewechselt haben, tragen noch Schritt-Zeilen des alten Workflows.
 * Neue Wechsel räumt ProjectObserver selbst auf; dieser Befehl erledigt die vorhandenen Fälle. Mit --dry-run wird nur gezählt.
 */
class PurgeForeignWorkflowSteps extends Command
{
    protected $signature = 'projects:purge-foreign-workflow-steps {--dry-run : Nur zählen, nichts löschen} {--tenant= : Nur diese Organisation (ID)}';

    protected $description = 'Entfernt Schritt-Zeilen anderer Workflows von Projekten (nach einem Workflow-Wechsel)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $projects = Project::query()->withoutGlobalScopes()
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('tenant_id', (int) $tenant))
            ->whereIn('id', function ($query) {
                $query->select('pws.project_id')->from('project_workflow_steps as pws')
                    ->join('projects as p', 'p.id', '=', 'pws.project_id')
                    ->join('workflow_steps as ws', 'ws.id', '=', 'pws.workflow_step_id')
                    ->where(fn ($inner) => $inner->whereNull('p.workflow_id')->orWhereColumn('ws.workflow_id', '!=', 'p.workflow_id'));
            })
            ->get();

        $rows = 0;
        foreach ($projects as $project) {
            if ($dry) {
                $rows += DB::table('project_workflow_steps as pws')->join('workflow_steps as ws', 'ws.id', '=', 'pws.workflow_step_id')
                    ->where('pws.project_id', $project->id)
                    ->when($project->workflow_id, fn ($query) => $query->where('ws.workflow_id', '!=', $project->workflow_id))->count();

                continue;
            }
            $rows += ProjectWorkflowStep::purgeForeignSteps($project);
        }

        $this->info(($dry ? 'Würde entfernen: ' : 'Entfernt: ').$rows.' Schritt-Zeilen in '.$projects->count().' Projekten.');

        return self::SUCCESS;
    }
}
