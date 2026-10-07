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
    'milestone_title', 'duration_days', 'duration_locked', 'is_start', 'is_end',
])]
#[ObservedBy(ProjectWorkflowStepObserver::class)]
class ProjectWorkflowStep extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'duration_locked' => 'boolean',
            'started_at' => 'datetime',
            'due_date' => 'date',
            'milestone_done_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_start' => 'boolean',
            'is_end' => 'boolean',
        ];
    }

    /**
     * Terminberechnung: milestone_title/duration_days/is_start/is_end sind
     * pro Projekt NULL = "wie Vorlage", bis jemand hier bewusst abweicht
     * (siehe Migrations-Kommentar). Diese vier Helfer liefern immer den
     * tatsächlich wirksamen Wert.
     */
    public function effectiveMilestoneTitle(): ?string
    {
        return $this->milestone_title ?? $this->workflowStep->milestone_title;
    }

    public function effectiveDurationDays(): int
    {
        return $this->duration_days ?? $this->workflowStep->duration_days;
    }

    public function effectiveIsStart(): bool
    {
        return $this->is_start ?? $this->workflowStep->is_start;
    }

    public function effectiveIsEnd(): bool
    {
        return $this->is_end ?? $this->workflowStep->is_end;
    }

    /**
     * Nur sichtbare Termin-Schritte des aktuell zugewiesenen Workflows
     * dürfen Start/Ende des Projekts steuern. Lifecycle-Schritte ohne
     * Termin erscheinen nicht in der Terminverwaltung und wären als
     * Datumsquelle deshalb irreführend.
     */
    public function isScheduleStepForCurrentWorkflow(): bool
    {
        return (bool) $this->workflowStep?->has_due_date
            && $this->workflowStep->workflow_id === $this->project?->workflow_id;
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
