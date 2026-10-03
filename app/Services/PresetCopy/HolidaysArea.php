<?php

namespace App\Services\PresetCopy;

use App\Models\Holiday;
use Illuminate\Support\Collection;

/**
 * Feiertage, angeboten werden ganze Jahre (Ralf, 2026-10-03: zehn Jahre einzeln anzukreuzen ist nicht zielführend;
 * einzelne Feiertage lassen sich im Ziel danach löschen). Identität eines Feiertags = Datum + Name. Gibt es das Jahr im
 * Ziel schon, gleicht "Überschreiben" gleiche Feiertage an (Wochentag, Bemerkung, aktiv) und ergänzt fehlende; zusätzliche
 * im Ziel bleiben. Auf einen Feiertag verweist nichts anderes.
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
        $targetYears = $this->holidays($targetTenantId)->groupBy(fn (Holiday $h) => $h->date->format('Y'));

        return $this->holidays($sourceTenantId)->groupBy(fn (Holiday $h) => $h->date->format('Y'))->map(function (Collection $holidays, string $year) use ($targetYears) {
            $inTarget = $targetYears->get($year);

            return [
                'key' => 'y:'.$year,
                'label' => $year,
                'parent' => null,
                'conflict' => $inTarget !== null,
                'renamable' => false,
                'note' => $holidays->count().' '.__('Feiertage').($holidays->where('active', false)->isNotEmpty() ? ' ('.$holidays->where('active', false)->count().' '.__('inaktiv').')' : ''),
                'hint' => $inTarget ? __('Im Ziel gibt es für dieses Jahr schon :count Feiertage. „Überschreiben“ gleicht gleiche Feiertage an und ergänzt fehlende; zusätzliche im Ziel bleiben.', ['count' => $inTarget->count()]) : null,
            ];
        })->values()->all();
    }

    /** Ein Jahr wird komplett übernommen, einschließlich des Inaktiv-Status der einzelnen Feiertage. */
    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $existing = $this->holidays($targetTenantId)->keyBy(fn (Holiday $h) => $this->identity($h));
        $targetYears = $existing->groupBy(fn (Holiday $h) => $h->date->format('Y'));

        foreach ($this->holidays($sourceTenantId)->groupBy(fn (Holiday $h) => $h->date->format('Y')) as $year => $holidays) {
            $choice = $choices['y:'.$year] ?? null;
            if ($choice === null) {
                continue;
            }
            if ($targetYears->has((string) $year) && $choice !== 'overwrite') {
                $report->add($this->label(), (string) $year, __('übersprungen (gibt es schon)'));

                continue;
            }

            $created = 0;
            $updated = 0;
            foreach ($holidays as $source) {
                $data = $source->only(['weekday', 'remarks', 'active']);
                if ($target = $existing->get($this->identity($source))) {
                    $target->update($data);
                    $updated++;
                } else {
                    Holiday::query()->withoutGlobalScope('tenant')->create($data + [
                        'tenant_id' => $targetTenantId, 'name' => $source->name, 'date' => $source->date->format('Y-m-d'),
                    ]);
                    $created++;
                }
            }
            $report->add($this->label(), (string) $year, __(':created angelegt, :updated angeglichen', ['created' => $created, 'updated' => $updated]));
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
