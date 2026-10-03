<?php

namespace App\Services\PresetCopy;

use App\Models\FunctionGroup;
use Illuminate\Support\Collection;

/**
 * Freigaben der Funktionsgruppen: Die Gruppen selbst verwaltet die Heimat-Organisation, jede andere Organisation
 * bekommt nur die freigeschaltet, die sie nutzen darf. Hier werden Freigaben der Quelle ins Ziel übertragen, damit
 * Workflows und Aufwandsschablonen dort keine Gruppen verlieren. Es werden nur Freigaben ergänzt, nie entzogen
 * (daher kein Überschreiben). Für die Heimat-Organisation als Ziel gibt es nichts zu tun - sie sieht alle Gruppen.
 */
class FunctionGroupAccessArea implements PresetArea
{
    public function key(): string
    {
        return 'function-group-access';
    }

    public function label(): string
    {
        return __('Funktionsgruppen (Freigaben)');
    }

    public function hint(): string
    {
        return __('Funktionsgruppen werden ausschließlich beim Heimat-Organisation angelegt und können von dort für die anderen Organisationen freigeschaltet werden. Dies können Sie hier durchführen. Bereits freigeschaltete Inhalte bleiben unverändert; es wird nie eine Freigabe entzogen.');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        if ($this->targetSeesAll($targetTenantId)) {
            return [];
        }
        $already = $this->groups($targetTenantId)->pluck('id');

        return $this->groups($sourceTenantId)->map(fn (FunctionGroup $group) => [
            'key' => 'f:'.$group->id,
            'label' => $group->name.($group->active ? '' : ' [i]'),
            'parent' => null,
            'conflict' => $already->contains($group->id),
            'renamable' => false,
            'overwritable' => false,
            'note' => $group->short_name ?? null,
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        if ($this->targetSeesAll($targetTenantId)) {
            return;
        }
        $already = $this->groups($targetTenantId)->pluck('id');

        foreach ($this->groups($sourceTenantId) as $group) {
            if (! isset($choices['f:'.$group->id])) {
                continue;
            }
            if ($already->contains($group->id)) {
                $report->add($this->label(), $group->name, __('bereits freigeschaltet'));

                continue;
            }
            $group->availableTenants()->syncWithoutDetaching([$targetTenantId]);
            $report->add($this->label(), $group->name, __('freigeschaltet'));
        }
    }

    private function targetSeesAll(int $targetTenantId): bool
    {
        return FunctionGroup::catalogTenantId($targetTenantId) === $targetTenantId;
    }

    /** @return Collection<int, FunctionGroup> */
    private function groups(int $tenantId): Collection
    {
        return FunctionGroup::query()->availableForTenant($tenantId)->orderBy('function_groups.sort')->orderBy('function_groups.id')->get();
    }
}
