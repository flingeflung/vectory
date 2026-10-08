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

    /**
     * Standard-Erinnerungen an einen anderen Workflow übertragen (Neue Version, Kopieren, Konfiguration übernehmen). Die Mail-Vorlage
     * bleibt dieselbe (Vorlagen gehören der Organisation), Schritte und Meilensteine werden über die Zuordnungen übersetzt; Einträge, deren
     * Bezug nicht übertragen wurde, entfallen. Bei einer anderen Organisation entfallen Erinnerungen, deren Vorlage dort nicht existiert.
     *
     * @param  array<int, int>  $stepIdMap
     * @param  array<int, int>  $milestoneIdMap
     */
    public static function copyToWorkflow(Workflow $source, Workflow $target, array $stepIdMap, array $milestoneIdMap): void
    {
        $rows = static::query()->withoutGlobalScopes()->where('workflow_id', $source->id)->orderBy('id')->get();

        foreach ($rows as $row) {
            if (! isset($stepIdMap[$row->workflow_step_id])) {
                continue;
            }
            if ($row->reference_milestone_id !== null && ! isset($milestoneIdMap[$row->reference_milestone_id])) {
                continue;
            }
            if ($row->reference_step_id !== null && ! isset($stepIdMap[$row->reference_step_id])) {
                continue;
            }
            if ($source->tenant_id !== $target->tenant_id) {
                // Vorlagen und Funktionsgruppen gehören der jeweiligen Organisation: nicht übertragbar
                continue;
            }

            static::query()->withoutGlobalScopes()->create([
                'tenant_id' => $target->tenant_id,
                'workflow_id' => $target->id,
                'workflow_step_id' => $stepIdMap[$row->workflow_step_id],
                'mail_template_id' => $row->mail_template_id,
                'reference_type' => $row->reference_type,
                'reference_milestone_id' => $row->reference_milestone_id !== null ? $milestoneIdMap[$row->reference_milestone_id] : null,
                'reference_step_id' => $row->reference_step_id !== null ? $stepIdMap[$row->reference_step_id] : null,
                'offset_days' => $row->offset_days,
                'only_if_in_step' => $row->only_if_in_step,
                'function_group_ids' => $row->function_group_ids,
            ]);
        }
    }
}
