<?php

namespace App\Services\PresetCopy;

use App\Models\FunctionGroup;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Workflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aufwandsschablonen mit ihren geplanten Stunden je Funktionsgruppe (Identität = Name).
 * Der zugeordnete Workflow wird über den Namen ins Ziel übersetzt; gibt es ihn dort nicht, wird er als
 * Voraussetzung mit angelegt. Überschreiben nur, solange die Schablone im Ziel von keinem Projekt verwendet wird.
 */
class ProjectTemplatesArea implements PresetArea
{
    private const COLUMNS = [
        'format', 'unrestricted_function_groups', 'reusable_content_share', 'languages_count', 'product_maturity',
        'product_change_delays', 'contact_availability', 'localizer_availability', 'software_share', 'product_complexity',
        'print_variants_count', 'images_count', 'duration_value', 'duration_unit', 'remarks', 'use_characteristics', 'active',
    ];

    public function key(): string
    {
        return 'project-templates';
    }

    public function label(): string
    {
        return __('Aufwandsschablonen');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->templates($targetTenantId)->keyBy('name');

        return $this->templates($sourceTenantId)->map(function (ProjectTemplate $template) use ($existing) {
            $target = $existing->get($template->name);
            $used = $target && $this->isUsed($target);

            return [
                'key' => 't:'.$template->id,
                'label' => $template->name,
                'parent' => null,
                'conflict' => $target !== null,
                'renamable' => true,
                'overwritable' => ! $used,
                'note' => ($template->workflow_id ? __('Workflow „:name“', ['name' => Workflow::query()->withoutGlobalScope('tenant')->whereKey($template->workflow_id)->value('name')]) : __('ohne Workflow'))
                    .($used ? ' · '.__('im Ziel in Projekten verwendet - Überschreiben nicht möglich') : ''),
            ];
        })->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $workflows = new WorkflowsArea;

        foreach ($this->templates($sourceTenantId) as $source) {
            $choice = $choices['t:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targets = $this->templates($targetTenantId);
            $existing = $targets->firstWhere('name', $source->name);

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));

                continue;
            }
            if ($existing && $choice === 'overwrite' && $this->isUsed($existing)) {
                $report->add($this->label(), $source->name, __('übersprungen (im Ziel in Projekten verwendet)'));

                continue;
            }

            $workflowId = $source->workflow_id
                ? $workflows->ensureInTarget(Workflow::query()->withoutGlobalScope('tenant')->with(['steps' => fn ($q) => $q->withoutGlobalScope('tenant')])->findOrFail($source->workflow_id), $targetTenantId, $report)
                : null;

            if ($existing && $choice === 'overwrite') {
                $existing->update($source->only(self::COLUMNS) + ['workflow_id' => $workflowId]);
                DB::table('project_template_function_group')->where('project_template_id', $existing->id)->delete();
                $skipped = $this->copyHours($source, $existing, $targetTenantId);
                $report->add($this->label(), $source->name, __('überschrieben').$this->note($skipped));

                continue;
            }

            $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
            $new = ProjectTemplate::query()->withoutGlobalScope('tenant')->create($source->only(self::COLUMNS) + [
                'tenant_id' => $targetTenantId, 'name' => $name, 'workflow_id' => $workflowId,
                'sort' => (int) $targets->max('sort') + 1,
            ]);
            $skipped = $this->copyHours($source, $new, $targetTenantId);
            $report->add($this->label(), $source->name, ($existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt')).$this->note($skipped));
        }
    }

    /** @return list<string> Funktionsgruppen, die im Ziel nicht zur Verfügung stehen */
    private function copyHours(ProjectTemplate $source, ProjectTemplate $target, int $targetTenantId): array
    {
        $available = FunctionGroup::query()->availableForTenant($targetTenantId)->pluck('function_groups.id')->all();
        $skipped = [];

        foreach (DB::table('project_template_function_group')->where('project_template_id', $source->id)->get() as $row) {
            if (in_array($row->function_group_id, $available, true)) {
                DB::table('project_template_function_group')->insert([
                    'tenant_id' => $targetTenantId, 'project_template_id' => $target->id, 'function_group_id' => $row->function_group_id,
                    'planned_hours' => $row->planned_hours, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $skipped[$row->function_group_id] = FunctionGroup::query()->withoutGlobalScope('tenant')->whereKey($row->function_group_id)->value('name') ?? (string) $row->function_group_id;
            }
        }

        return array_values($skipped);
    }

    private function isUsed(ProjectTemplate $template): bool
    {
        return Project::query()->withoutGlobalScope('tenant')->where('project_template_id', $template->id)->exists();
    }

    private function note(array $skipped): string
    {
        return $skipped === [] ? '' : ' – '.__('Funktionsgruppen im Ziel nicht verfügbar, Stunden dafür ausgelassen: :names', ['names' => implode(', ', $skipped)]);
    }

    private function freeName(string $name, array $taken): string
    {
        $candidate = $name.' ('.__('Kopie').')';
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = $name.' ('.__('Kopie').' '.$i.')';
        }

        return $candidate;
    }

    /** @return Collection<int, ProjectTemplate> */
    private function templates(int $tenantId): Collection
    {
        return ProjectTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('id')->get();
    }
}
