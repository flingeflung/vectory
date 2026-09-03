<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\Task;

class ProjectObserver
{
    public function updated(Project $project): void
    {
        if ($project->wasChanged('workflow_id')) {
            Task::syncWorkflowTasksForProject($project);
        }
    }
}
