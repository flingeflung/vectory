<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Erinnerungsmail zu einem Projekt (Ralf, 2026-10-09). Das Sendedatum ergibt sich aus dem Bezugstermin (festes Datum, Meilenstein oder
 * benanntes Phasenende) plus Abstand in Kalendertagen und wird von MailTimerService berechnet; Betreff und Text stammen aus der Mail-Vorlage.
 */
#[Fillable(['tenant_id', 'project_id', 'workflow_mail_timer_id', 'workflow_step_id', 'mail_template_id', 'reference_type', 'reference_milestone_id', 'reference_step_id', 'fixed_date', 'offset_days', 'only_if_in_step', 'function_group_ids', 'send_date', 'sent_at', 'skipped_at', 'last_error', 'created_by_user_id'])]
class MailTimer extends Model
{
    use BelongsToTenant;

    public const REFERENCE_FIXED = 'fixed';

    public const REFERENCE_MILESTONE = 'milestone';

    public const REFERENCE_PHASE_END = 'phase_end';

    protected function casts(): array
    {
        return [
            'fixed_date' => 'date',
            'send_date' => 'date',
            'sent_at' => 'datetime',
            'skipped_at' => 'datetime',
            'offset_days' => 'integer',
            'only_if_in_step' => 'boolean',
            'function_group_ids' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function mailTemplate(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class)->withoutGlobalScopes();
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id')->withoutGlobalScopes();
    }

    /** offen = noch nicht gesendet und nicht übersprungen */
    public function isPending(): bool
    {
        return $this->sent_at === null && $this->skipped_at === null;
    }
}
