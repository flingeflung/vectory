<?php

namespace App\Observers;

use App\Models\ProjectWorkflowStepPerson;
use App\Models\Task;

/**
 * Greift, sobald es eine Zuweisungs-UI für die Schritt-Override-Zuweisung
 * gibt (project_workflow_step_people ist aktuell nur per Import befüllt).
 */
class ProjectWorkflowStepPersonObserver
{
    public function saved(ProjectWorkflowStepPerson $assignment): void
    {
        $this->sync($assignment);
    }

    public function deleted(ProjectWorkflowStepPerson $assignment): void
    {
        $this->sync($assignment);
    }

    private function sync(ProjectWorkflowStepPerson $assignment): void
    {
        $project = $assignment->projectWorkflowStep?->project;

        if ($project) {
            Task::syncWorkflowTasksForProject($project);
        }
    }
}
