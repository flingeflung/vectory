<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hält die Auswahl fest, die der TR beim Auslösen eines Freigabe-WFS
 * trifft (Quell-PDF + Zielordner für beide möglichen Ausgänge), bis der
 * externe Empfänger über den Mail-Link reagiert - siehe
 * ProjectWorkflowStepController::activate() (Anlage) und
 * WorkflowStepFreigabeActionController (Reaktion, ganz ohne Login).
 */
#[Fillable([
    'tenant_id', 'project_id', 'project_workflow_step_id', 'triggered_by_person_id',
    'source_pdf_path', 'freigabe_target_path', 'korrektur_target_path',
    'status', 'korrektur_kommentar', 'decided_at',
])]
class WorkflowStepFreigabeRequest extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FREIGEGEBEN = 'freigegeben';

    public const STATUS_KORREKTUR_HOCHGELADEN = 'korrektur_hochgeladen';

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(ProjectWorkflowStep::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'triggered_by_person_id')->withoutGlobalScope('tenant');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
