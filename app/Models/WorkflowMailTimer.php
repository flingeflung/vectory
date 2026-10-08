<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Standard-Erinnerung am Workflow-Schritt (Vorlage): Mail-Vorlage, Bezugstermin, Abstand, Funktionsgruppen. Jedes Projekt bekommt eine Kopie. */
#[Fillable(['tenant_id', 'workflow_id', 'workflow_step_id', 'mail_template_id', 'reference_type', 'reference_milestone_id', 'reference_step_id', 'offset_days', 'only_if_in_step', 'function_group_ids'])]
class WorkflowMailTimer extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['offset_days' => 'integer', 'only_if_in_step' => 'boolean', 'function_group_ids' => 'array'];
    }

    public function mailTemplate(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class)->withoutGlobalScopes();
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id')->withoutGlobalScopes();
    }
}
