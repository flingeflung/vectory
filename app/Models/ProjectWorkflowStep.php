<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProjectWorkflowStepObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id', 'project_id', 'workflow_step_id', 'legacy_id', 'sort', 'is_current',
    'started_at', 'due_date', 'milestone_done_at', 'completed_at', 'completed_by_person_id',
])]
#[ObservedBy(ProjectWorkflowStepObserver::class)]
class ProjectWorkflowStep extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'started_at' => 'datetime',
            'due_date' => 'date',
            'milestone_done_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'completed_by_person_id');
    }

    public function people(): HasMany
    {
        return $this->hasMany(ProjectWorkflowStepPerson::class);
    }
}
