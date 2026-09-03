<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\ProjectWorkflowStepPersonObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_workflow_step_id', 'function_group_id', 'person_id'])]
#[ObservedBy(ProjectWorkflowStepPersonObserver::class)]
class ProjectWorkflowStepPerson extends Model
{
    use BelongsToTenant;

    public function projectWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflowStep::class);
    }

    public function functionGroup(): BelongsTo
    {
        return $this->belongsTo(FunctionGroup::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
