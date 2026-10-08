<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Meilenstein in der Workflow-Vorlage (Vy-Meilenstein, docs/ablaufplan-konzept.md). */
#[Fillable(['tenant_id', 'workflow_id', 'name', 'sort', 'anchor_type', 'anchor_workflow_step_id', 'offset_days', 'is_market_launch', 'check_direction'])]
class WorkflowMilestone extends Model
{
    use BelongsToTenant;

    public const ANCHOR_WORKFLOW_START = 'workflow_start';

    public const ANCHOR_WORKFLOW_END = 'workflow_end';

    public const ANCHOR_STEP_START = 'step_start';

    public const ANCHOR_STEP_END = 'step_end';

    public const ANCHOR_FIXED = 'fixed';

    protected function casts(): array
    {
        return ['offset_days' => 'integer', 'is_market_launch' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class)->withoutGlobalScope('tenant');
    }
}
