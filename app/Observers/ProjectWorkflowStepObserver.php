<?php

namespace App\Observers;

use App\Models\ProjectWorkflowStep;
use App\Models\Task;

/**
 * Greift, sobald es eine "nächster Schritt"-Aktion gibt (aktuell noch nicht
 * gebaut - is_current wird bisher nur per Import per DB::upsert() gesetzt,
 * das umgeht Eloquent-Events bewusst und braucht darum SyncTasksFromWorkflow
 * als expliziten Nachlauf).
 */
class ProjectWorkflowStepObserver
{
    public function saved(ProjectWorkflowStep $projectWorkflowStep): void
    {
        if ($projectWorkflowStep->wasChanged('is_current') && $projectWorkflowStep->project) {
            Task::syncWorkflowTasksForProject($projectWorkflowStep->project);
        }
    }
}
