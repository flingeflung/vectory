<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Meilenstein am Projekt (berechnetes Datum in `date`, siehe ProjectScheduler). */
#[ObservedBy(\App\Observers\ProjectMilestoneObserver::class)]
#[Fillable(['tenant_id', 'project_id', 'workflow_milestone_id', 'name', 'sort', 'anchor_type', 'anchor_workflow_step_id', 'offset_days', 'fixed_date', 'is_market_launch', 'check_direction', 'date', 'reached_at'])]
class ProjectMilestone extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
            'fixed_date' => 'date',
            'date' => 'date',
            'reached_at' => 'datetime',
            'is_market_launch' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScope('tenant');
    }
}
