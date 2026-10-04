<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Einsatzzeitraum einer Funktionsgruppe innerhalb eines Workflows (Ralf, 2026-10-04): von welchem bis zu
 * welchem Arbeitsschritt (lifecycle_status 2 = "In Bearbeitung") die Gruppe gebraucht wird. Kein Eintrag
 * bedeutet: ganze Breite. "Planung", "Beendet" und "Verworfen" haben keinen Zeitanteil, weil dort niemand
 * etwas zu tun hat. Der Einsatzplan friert wie alles andere mit dem Workflow ein (Veröffentlichen).
 */
#[Fillable(['tenant_id', 'workflow_id', 'function_group_id', 'from_step_id', 'to_step_id'])]
class WorkflowGroupWindow extends Model
{
    use BelongsToTenant;

    /** Schritte, die im Einsatzplan einen Zeitanteil haben: "In Bearbeitung". */
    public const WORK_LIFECYCLE_STATUS = 2;

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Einsatzplan an einen anderen Workflow übertragen (Neue Version, Kopieren, Konfiguration übernehmen).
     * $stepIdMap: alte Schritt-ID => neue Schritt-ID. $availableGroupIds: nur diese Gruppen übernehmen
     * (null = alle).
     *
     * @param  array<int, int>  $stepIdMap
     * @param  list<int>|null  $availableGroupIds
     */
    public static function copyToWorkflow(Workflow $source, Workflow $target, array $stepIdMap, ?array $availableGroupIds = null): void
    {
        /** @var Collection<int, self> $windows */
        $windows = static::query()->withoutGlobalScope('tenant')->where('workflow_id', $source->id)->get();

        foreach ($windows as $window) {
            if ($availableGroupIds !== null && ! in_array($window->function_group_id, $availableGroupIds, true)) {
                continue;
            }
            static::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => $target->tenant_id,
                'workflow_id' => $target->id,
                'function_group_id' => $window->function_group_id,
                'from_step_id' => $window->from_step_id ? ($stepIdMap[$window->from_step_id] ?? null) : null,
                'to_step_id' => $window->to_step_id ? ($stepIdMap[$window->to_step_id] ?? null) : null,
            ]);
        }
    }
}
