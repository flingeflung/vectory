<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\Task;
use Illuminate\Console\Command;

/**
 * Baut die automatisch aus dem Workflow abgeleiteten Aufgaben (Task::source
 * = workflow_step) für alle Projekte mit aktuellem WFS neu auf. Einmalig
 * nötig für den Altbestand aus dem Vietto-Import; danach übernimmt
 * Task::syncWorkflowTasksForProject() das laufend an den Schreibstellen
 * (z.B. ProjectController@update).
 */
class SyncTasksFromWorkflow extends Command
{
    protected $signature = 'tasks:sync-from-workflow';

    protected $description = 'Aus dem WFS abgeleitete Aufgaben für alle Projekte neu aufbauen';

    public function handle(): int
    {
        $projectIds = ProjectWorkflowStep::query()->where('is_current', true)->pluck('project_id')->unique();

        $count = 0;
        Project::query()->whereIn('id', $projectIds)->each(function (Project $project) use (&$count) {
            Task::syncWorkflowTasksForProject($project);
            $count++;
        });

        $this->info("Aufgaben für {$count} Projekte synchronisiert.");

        return self::SUCCESS;
    }
}
