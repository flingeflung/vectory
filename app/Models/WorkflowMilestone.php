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

    /**
     * Meilensteine an einen anderen Workflow übertragen (Neue Version, Kopieren, Konfiguration übernehmen).
     * $stepIdMap: alte Schritt-ID => neue Schritt-ID; Meilensteine, deren Bezugsschritt nicht übertragen wurde, entfallen.
     *
     * @param  array<int, int>  $stepIdMap
     */
    public static function copyToWorkflow(Workflow $source, Workflow $target, array $stepIdMap): void
    {
        $rows = static::query()->withoutGlobalScopes()->where('workflow_id', $source->id)->orderBy('sort')->orderBy('id')->get();

        foreach ($rows as $row) {
            $stepId = $row->anchor_workflow_step_id;
            if ($stepId !== null && ! isset($stepIdMap[$stepId])) {
                continue;
            }
            static::query()->withoutGlobalScopes()->create([
                'tenant_id' => $target->tenant_id,
                'workflow_id' => $target->id,
                'name' => $row->name,
                'sort' => $row->sort,
                'anchor_type' => $row->anchor_type,
                'anchor_workflow_step_id' => $stepId !== null ? $stepIdMap[$stepId] : null,
                'offset_days' => $row->offset_days,
                'is_market_launch' => $row->is_market_launch,
                'check_direction' => $row->check_direction,
            ]);
        }
    }
}
