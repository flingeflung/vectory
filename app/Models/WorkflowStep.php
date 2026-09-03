<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'tenant_id', 'workflow_id', 'legacy_id', 'title', 'short_title', 'milestone_title',
    'sort', 'duration_days', 'is_active', 'is_start', 'is_end', 'is_market_launch',
    'has_due_date', 'send_email', 'duration_editable', 'show_in_translation',
    'js_function', 'js_function_param', 'description', 'email_text', 'msg_task_function_group_ids',
    'lifecycle_status',
])]
class WorkflowStep extends Model
{
    use BelongsToTenant;

    /**
     * Kastenfarben der Box-Ansicht je lifecycle_status (1-4), 1:1 aus
     * Viettos $wfs_bgcolors übernommen.
     */
    public const LIFECYCLE_COLORS = [
        1 => '#eeffee',
        2 => '#ccffcc',
        3 => '#66cc66',
        4 => '#cccccc',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_start' => 'boolean',
            'is_end' => 'boolean',
            'is_market_launch' => 'boolean',
            'has_due_date' => 'boolean',
            'send_email' => 'boolean',
            'duration_editable' => 'boolean',
            'show_in_translation' => 'boolean',
        ];
    }

    public function lifecycleColor(): string
    {
        return self::LIFECYCLE_COLORS[$this->lifecycle_status] ?? self::LIFECYCLE_COLORS[2];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function functionGroups(): BelongsToMany
    {
        return $this->belongsToMany(FunctionGroup::class, 'workflow_step_function_group');
    }
}
