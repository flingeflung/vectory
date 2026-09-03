<?php

namespace App\Observers;

use App\Models\ProjectPerson;
use App\Models\Task;

class ProjectPersonObserver
{
    public function saved(ProjectPerson $projectPerson): void
    {
        $this->sync($projectPerson);
    }

    public function deleted(ProjectPerson $projectPerson): void
    {
        $this->sync($projectPerson);
    }

    private function sync(ProjectPerson $projectPerson): void
    {
        if ($projectPerson->project) {
            Task::syncWorkflowTasksForProject($projectPerson->project);
        }
    }
}
