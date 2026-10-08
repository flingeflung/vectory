<?php

namespace App\Observers;

use App\Models\Holiday;
use App\Models\Project;
use App\Services\ProjectScheduler;

/** Feiertage fließen in die Terminrechnung ein (Ralf, 2026-10-04): jede Änderung rechnet die Projekte der Organisation neu. */
class HolidayObserver
{
    public function saved(Holiday $holiday): void
    {
        $this->recalculateTenant((int) $holiday->tenant_id);
    }

    public function deleted(Holiday $holiday): void
    {
        $this->recalculateTenant((int) $holiday->tenant_id);
    }

    private function recalculateTenant(int $tenantId): void
    {
        $scheduler = app(ProjectScheduler::class);
        Project::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->where('schedule_model', 2)->whereNotIn('status', [2, 3])->whereNotNull('start_date')->whereNotNull('workflow_id')
            ->each(fn (Project $project) => $scheduler->recalculate($project));
    }
}
