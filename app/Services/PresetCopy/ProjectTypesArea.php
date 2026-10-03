<?php

namespace App\Services\PresetCopy;

use App\Models\ProjectTypeMain;
use App\Models\ProjectTypeSub;
use Illuminate\Support\Collection;

/**
 * Projektarten: Hauptarten (Identität = Name) mit ihren Unterarten (Identität = Name innerhalb der
 * Hauptart). Wer eine Unterart wählt, bekommt die Hauptart automatisch mit. Überschreiben ändert
 * nur Eigenschaften (Farbe, Symbol, Format, aktiv) - die ID bleibt, bestehende Projekte sind nicht berührt.
 */
class ProjectTypesArea implements PresetArea
{
    public function key(): string
    {
        return 'project-types';
    }

    public function label(): string
    {
        return __('Projektarten');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $targetMains = $this->mains($targetTenantId)->keyBy('name');
        $targetSubs = $this->subs($targetTenantId);
        $sourceSubs = $this->subs($sourceTenantId);
        $items = [];

        foreach ($this->mains($sourceTenantId) as $main) {
            $targetMain = $targetMains->get($main->name);
            $items[] = [
                'key' => 'm:'.$main->id,
                'label' => $main->name,
                'parent' => null,
                'conflict' => $targetMain !== null,
                'renamable' => true,
                'note' => null,
            ];
            foreach ($sourceSubs->where('project_type_main_id', $main->id) as $sub) {
                $items[] = [
                    'key' => 's:'.$sub->id,
                    'label' => $sub->name,
                    'parent' => 'm:'.$main->id,
                    'conflict' => $targetMain && $targetSubs->where('project_type_main_id', $targetMain->id)->where('name', $sub->name)->isNotEmpty(),
                    'renamable' => true,
                    'note' => null,
                ];
            }
        }

        return $items;
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $sourceMains = $this->mains($sourceTenantId);
        $sourceSubs = $this->subs($sourceTenantId);

        // Gewählte Unterarten ziehen ihre Hauptart nach, auch wenn diese nicht angehakt war.
        $wantedMains = collect(array_keys($choices))->filter(fn ($k) => str_starts_with($k, 'm:'))->map(fn ($k) => (int) substr($k, 2));
        foreach (array_keys($choices) as $key) {
            if (str_starts_with($key, 's:') && ($sub = $sourceSubs->firstWhere('id', (int) substr($key, 2)))) {
                $wantedMains->push($sub->project_type_main_id);
            }
        }

        $mainMap = []; // Quell-Hauptart-ID => Ziel-Hauptart-ID
        foreach ($sourceMains->whereIn('id', $wantedMains->unique()->all()) as $main) {
            $explicit = isset($choices['m:'.$main->id]);
            $mainMap[$main->id] = $this->applyMain($main, $targetTenantId, $choices['m:'.$main->id] ?? 'copy', $explicit, $report);
        }

        foreach ($sourceSubs as $sub) {
            $key = 's:'.$sub->id;
            if (isset($choices[$key], $mainMap[$sub->project_type_main_id])) {
                $this->applySub($sub, $targetTenantId, $mainMap[$sub->project_type_main_id], $choices[$key], $report);
            }
        }
    }

    /** @return int Ziel-Hauptart-ID */
    private function applyMain(ProjectTypeMain $main, int $targetTenantId, string $choice, bool $explicit, PresetReport $report): int
    {
        $targetMains = $this->mains($targetTenantId);
        $existing = $targetMains->firstWhere('name', $main->name);

        // Nur als Voraussetzung einer Unterart dabei: vorhandene Hauptart unverändert verwenden.
        if ($existing && ! $explicit) {
            return $existing->id;
        }
        if ($existing && $choice === 'overwrite') {
            $existing->update(['active' => $main->active]);
            $report->add($this->label(), $main->name, __('überschrieben'));

            return $existing->id;
        }
        if ($existing && $choice !== 'rename') {
            $report->add($this->label(), $main->name, __('übersprungen (gibt es schon)'));

            return $existing->id;
        }

        $name = $existing ? $this->freeName($main->name, $targetMains->pluck('name')->all()) : $main->name;
        $new = ProjectTypeMain::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $targetTenantId,
            'name' => $name,
            'active' => $main->active,
            'sort' => (int) $targetMains->max('sort') + 1,
        ]);
        $report->add($this->label(), $main->name, $existing
            ? __('angelegt als „:name“', ['name' => $name])
            : ($explicit ? __('angelegt') : __('angelegt (als Voraussetzung)')));

        return $new->id;
    }

    private function applySub(ProjectTypeSub $sub, int $targetTenantId, int $targetMainId, string $choice, PresetReport $report): void
    {
        $siblings = $this->subs($targetTenantId)->where('project_type_main_id', $targetMainId);
        $existing = $siblings->firstWhere('name', $sub->name);
        $data = $sub->only(['active', 'format_type', 'symbol', 'color']);

        if ($existing && $choice === 'overwrite') {
            $existing->update($data);
            $report->add($this->label(), $sub->name, __('überschrieben'));

            return;
        }
        if ($existing && $choice !== 'rename') {
            $report->add($this->label(), $sub->name, __('übersprungen (gibt es schon)'));

            return;
        }

        $name = $existing ? $this->freeName($sub->name, $siblings->pluck('name')->all()) : $sub->name;
        ProjectTypeSub::query()->withoutGlobalScope('tenant')->create($data + [
            'tenant_id' => $targetTenantId,
            'project_type_main_id' => $targetMainId,
            'name' => $name,
            'sort' => (int) $siblings->max('sort') + 1,
        ]);
        $report->add($this->label(), $sub->name, $existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt'));
    }

    private function freeName(string $name, array $taken): string
    {
        $candidate = $name.' ('.__('Kopie').')';
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = $name.' ('.__('Kopie').' '.$i.')';
        }

        return $candidate;
    }

    /** @return Collection<int, ProjectTypeMain> */
    private function mains(int $tenantId): Collection
    {
        return ProjectTypeMain::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('id')->get();
    }

    /** @return Collection<int, ProjectTypeSub> */
    private function subs(int $tenantId): Collection
    {
        return ProjectTypeSub::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('id')->get();
    }
}
