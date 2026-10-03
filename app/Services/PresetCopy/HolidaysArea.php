<?php

namespace App\Services\PresetCopy;

use App\Models\Holiday;
use Illuminate\Support\Collection;

/**
 * Feiertage (Identität = Datum + Name), nach Jahren gruppiert. Überschreiben gleicht Wochentag, Bemerkung und
 * "aktiv" an; auf einen Feiertag verweist nichts anderes.
 */
class HolidaysArea implements PresetArea
{
    public function key(): string
    {
        return 'holidays';
    }

    public function label(): string
    {
        return __('Feiertage');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->holidays($targetTenantId)->map(fn (Holiday $h) => $this->identity($h))->flip();

        return $this->holidays($sourceTenantId)->map(fn (Holiday $holiday) => [
            'key' => 'h:'.$holiday->id,
            'label' => $holiday->date->format('d.m.Y').' – '.$holiday->name,
            'group' => (string) $holiday->date->format('Y'),
            'parent' => null,
            'conflict' => $existing->has($this->identity($holiday)),
            'renamable' => false,
            'note' => $holiday->active ? null : __('inaktiv'),
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $existing = $this->holidays($targetTenantId)->keyBy(fn (Holiday $h) => $this->identity($h));

        foreach ($this->holidays($sourceTenantId) as $source) {
            $choice = $choices['h:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $label = $source->date->format('d.m.Y').' '.$source->name;
            $data = $source->only(['weekday', 'remarks', 'active']);

            if ($target = $existing->get($this->identity($source))) {
                if ($choice === 'overwrite') {
                    $target->update($data);
                    $report->add($this->label(), $label, __('überschrieben'));
                } else {
                    $report->add($this->label(), $label, __('übersprungen (gibt es schon)'));
                }

                continue;
            }

            Holiday::query()->withoutGlobalScope('tenant')->create($data + [
                'tenant_id' => $targetTenantId, 'name' => $source->name, 'date' => $source->date->format('Y-m-d'),
            ]);
            $report->add($this->label(), $label, __('angelegt'));
        }
    }

    private function identity(Holiday $holiday): string
    {
        return $holiday->date->format('Y-m-d').'|'.mb_strtolower($holiday->name);
    }

    /** @return Collection<int, Holiday> */
    private function holidays(int $tenantId): Collection
    {
        return Holiday::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('date')->orderBy('name')->get();
    }
}
