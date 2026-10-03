<?php

namespace App\Services\PresetCopy;

use App\Models\FunctionGroup;
use App\Models\Project;
use App\Models\ProjectWorkflowStep;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Workflows mit ihren Schritten und den zugeordneten Funktionsgruppen (Identität = Name).
 *
 * Überschreiben ersetzt den Workflow im Ziel komplett (Schritte neu aufgebaut) - deshalb NUR, solange der
 * Workflow im Ziel unbenutzt UND noch ein Entwurf ist: Sobald ein Projekt ihn verwendet oder er veröffentlicht
 * wurde (Veröffentlichtes ändert man nur über "Neue Version"), bleibt nur Überspringen oder Umbenennen.
 * Funktionsgruppen, die dem Ziel nicht zur Verfügung stehen, werden bei den Schritten ausgelassen und im Bericht genannt.
 */
class WorkflowsArea implements PresetArea
{
    private const STEP_COLUMNS = [
        'title', 'short_title', 'milestone_title', 'sort', 'duration_days', 'is_active', 'is_start', 'is_end',
        'is_market_launch', 'has_due_date', 'send_email', 'show_in_translation', 'js_function', 'js_function_param',
        'description', 'email_text', 'msg_task_function_group_ids', 'lifecycle_status',
    ];

    public function key(): string
    {
        return 'workflows';
    }

    public function label(): string
    {
        return __('Workflows');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->workflows($targetTenantId)->keyBy('name');

        return $this->workflows($sourceTenantId)->map(function (Workflow $workflow) use ($existing) {
            $target = $existing->get($workflow->name);
            $blocked = $target ? $this->blockReason($target) : null;

            return [
                'key' => 'w:'.$workflow->id,
                'label' => $workflow->name,
                'parent' => null,
                'conflict' => $target !== null,
                'renamable' => true,
                'overwritable' => $target === null || $blocked === null,
                'note' => $workflow->steps->count().' '.__('Schritte').($blocked ? ' · '.__('im Ziel :reason - Überschreiben nicht möglich', ['reason' => $blocked]) : ''),
            ];
        })->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $map = []; // Quell-Workflow-ID => Ziel-Workflow-ID

        foreach ($this->workflows($sourceTenantId) as $source) {
            $choice = $choices['w:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targets = $this->workflows($targetTenantId);
            $existing = $targets->firstWhere('name', $source->name);

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));
                $map[$source->id] = $existing->id;

                continue;
            }

            if ($existing && $choice === 'overwrite') {
                if ($reason = $this->blockReason($existing)) {
                    $report->add($this->label(), $source->name, __('übersprungen (im Ziel :reason)', ['reason' => $reason]));
                    $map[$source->id] = $existing->id;

                    continue;
                }
                WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $existing->id)->delete();
                $existing->update($source->only(['short_name', 'description', 'active', 'published_at']));
                $notes = $this->copySteps($source, $existing, $targetTenantId);
                $map[$source->id] = $existing->id;
                $report->add($this->label(), $source->name, __('überschrieben').$this->note($notes));

                continue;
            }

            $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
            [$new, $notes] = $this->create($source, $targetTenantId, $name);
            $map[$source->id] = $new->id;
            $report->add($this->label(), $source->name, ($existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt')).$this->note($notes));
        }

        // Nachfolger-Verweise (Versionskette) nur zwischen mitgenommenen Workflows.
        foreach ($this->workflows($sourceTenantId)->whereNotNull('superseded_by_id') as $source) {
            if (isset($map[$source->id], $map[$source->superseded_by_id]) && $map[$source->id] !== $map[$source->superseded_by_id]) {
                Workflow::query()->withoutGlobalScope('tenant')->whereKey($map[$source->id])->update(['superseded_by_id' => $map[$source->superseded_by_id]]);
            }
        }
    }

    /**
     * Für andere Bereiche (z.B. Aufwandsschablonen): den gleichnamigen Workflow im Ziel verwenden oder, falls es
     * ihn dort nicht gibt, als Voraussetzung anlegen.
     */
    public function ensureInTarget(Workflow $source, int $targetTenantId, PresetReport $report): int
    {
        $existing = $this->workflows($targetTenantId)->firstWhere('name', $source->name);
        if ($existing) {
            return $existing->id;
        }

        [$new, $notes] = $this->create($source, $targetTenantId, $source->name);
        $report->add($this->label(), $source->name, __('angelegt (als Voraussetzung)').$this->note($notes));

        return $new->id;
    }

    /** @return array{0: Workflow, 1: list<string>} */
    private function create(Workflow $source, int $targetTenantId, string $name): array
    {
        $new = Workflow::query()->withoutGlobalScope('tenant')->create($source->only(['short_name', 'description', 'active', 'published_at']) + [
            'tenant_id' => $targetTenantId,
            'name' => $name,
            'sort' => (int) $this->workflows($targetTenantId)->max('sort') + 1,
        ]);

        return [$new, $this->copySteps($source, $new, $targetTenantId)];
    }

    /** @return list<string> Namen der Funktionsgruppen, die im Ziel nicht zur Verfügung stehen */
    private function copySteps(Workflow $source, Workflow $target, int $targetTenantId): array
    {
        $available = FunctionGroup::query()->availableForTenant($targetTenantId)->pluck('function_groups.id')->all();
        $missing = [];
        $stepMap = [];

        foreach ($source->steps as $step) {
            $new = WorkflowStep::query()->withoutGlobalScope('tenant')->create($step->only(self::STEP_COLUMNS) + [
                'tenant_id' => $targetTenantId,
                'workflow_id' => $target->id,
            ]);
            $stepMap[$step->id] = $new->id;

            foreach (DB::table('workflow_step_function_group')->where('workflow_step_id', $step->id)->pluck('function_group_id') as $groupId) {
                if (in_array($groupId, $available, true)) {
                    DB::table('workflow_step_function_group')->insert([
                        'tenant_id' => $targetTenantId, 'workflow_step_id' => $new->id, 'function_group_id' => $groupId,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    $missing[$groupId] = FunctionGroup::query()->withoutGlobalScope('tenant')->whereKey($groupId)->value('name') ?? (string) $groupId;
                }
            }
        }

        // Schritt, der nach einer Freigabe folgt: nur innerhalb desselben Workflows übersetzbar.
        foreach ($source->steps->whereNotNull('after_freigabe_workflow_step_id') as $step) {
            if (isset($stepMap[$step->after_freigabe_workflow_step_id])) {
                WorkflowStep::query()->withoutGlobalScope('tenant')->whereKey($stepMap[$step->id])->update(['after_freigabe_workflow_step_id' => $stepMap[$step->after_freigabe_workflow_step_id]]);
            }
        }

        return array_values($missing);
    }

    /** Grund, warum der Zielworkflow nicht überschrieben werden darf - null = darf. */
    private function blockReason(Workflow $target): ?string
    {
        if ($target->isPublished()) {
            return __('veröffentlicht');
        }
        $used = Project::query()->withoutGlobalScope('tenant')->where('workflow_id', $target->id)->exists()
            || ProjectWorkflowStep::query()->withoutGlobalScope('tenant')
                ->whereIn('workflow_step_id', WorkflowStep::query()->withoutGlobalScope('tenant')->where('workflow_id', $target->id)->select('id'))->exists();

        return $used ? __('in Projekten verwendet') : null;
    }

    private function note(array $missingGroups): string
    {
        return $missingGroups === [] ? '' : ' – '.__('Funktionsgruppen im Ziel nicht verfügbar, bei den Schritten ausgelassen: :names', ['names' => implode(', ', $missingGroups)]);
    }

    private function freeName(string $name, array $taken): string
    {
        $candidate = $name.' ('.__('Kopie').')';
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = $name.' ('.__('Kopie').' '.$i.')';
        }

        return $candidate;
    }

    /** @return Collection<int, Workflow> */
    private function workflows(int $tenantId): Collection
    {
        return Workflow::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->with(['steps' => fn ($q) => $q->withoutGlobalScope('tenant')])->orderBy('sort')->orderBy('id')->get();
    }
}
