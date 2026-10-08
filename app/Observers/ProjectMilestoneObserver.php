<?php

namespace App\Observers;

use App\Models\ProjectMilestone;
use App\Services\ProjectScheduler;

/** Meilenstein geändert (Bezug, Abstand, festes Datum) -> sein Datum neu rechnen. */
class ProjectMilestoneObserver
{
    public function saved(ProjectMilestone $milestone): void
    {
        if ($milestone->wasChanged(['anchor_type', 'anchor_workflow_step_id', 'offset_days', 'fixed_date']) || $milestone->wasRecentlyCreated) {
            $project = $milestone->project;
            if ($project) {
                app(ProjectScheduler::class)->recalculate($project);
            }
        }
    }
}
