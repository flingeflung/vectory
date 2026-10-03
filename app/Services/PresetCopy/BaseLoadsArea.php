<?php

namespace App\Services\PresetCopy;

use App\Models\PlanningBaseLoad;
use Illuminate\Support\Collection;

/**
 * Grundlastbasis (Planung): angeboten werden die Jahre, die die Quelle angelegt hat. Übernommen wird immer in das
 * AKTUELLE Jahr des Ziels, mit Gültigkeit vom 1.1. bis 31.12. (Ralf, 2026-10-03: die Punkte kommen jedes Jahr wieder).
 * Gibt es im Ziel für das aktuelle Jahr schon Einträge, gleicht "Überschreiben" gleichnamige an (Art, Wert,
 * Gültigkeit) und ergänzt fehlende; zusätzliche im Ziel bleiben.
 */
class BaseLoadsArea implements PresetArea
{
    public function key(): string
    {
        return 'base-loads';
    }

    public function label(): string
    {
        return __('Grundlastbasis');
    }

    public function hint(): string
    {
        return __('Übernommen wird immer in das aktuelle Jahr (:year), mit Gültigkeit vom 1.1. bis 31.12.', ['year' => $this->targetYear()]);
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $inTarget = $this->rows($targetTenantId)->where('year', $this->targetYear());

        return $this->rows($sourceTenantId)->groupBy('year')->sortKeysDesc()->map(fn (Collection $rows, $year) => [
            'key' => 'y:'.$year,
            'label' => (string) $year,
            'parent' => null,
            'conflict' => $inTarget->isNotEmpty(),
            'renamable' => false,
            'note' => $rows->count().' '.__('Einträge').' → '.$this->targetYear(),
            'hint' => $inTarget->isNotEmpty()
                ? __('Im Ziel gibt es für :year schon :count Einträge. „Überschreiben“ gleicht gleichnamige an und ergänzt fehlende; zusätzliche im Ziel bleiben.', ['year' => $this->targetYear(), 'count' => $inTarget->count()])
                : null,
        ])->values()->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $year = $this->targetYear();

        foreach ($this->rows($sourceTenantId)->groupBy('year') as $sourceYear => $rows) {
            $choice = $choices['y:'.$sourceYear] ?? null;
            if ($choice === null) {
                continue;
            }
            $existing = $this->rows($targetTenantId)->where('year', $year)->keyBy(fn (PlanningBaseLoad $r) => mb_strtolower($r->name));

            if ($existing->isNotEmpty() && $choice !== 'overwrite') {
                $report->add($this->label(), (string) $sourceYear, __('übersprungen (im Ziel gibt es für :year schon Einträge)', ['year' => $year]));

                continue;
            }

            $created = 0;
            $updated = 0;
            foreach ($rows as $source) {
                $data = $source->only(['calculation_type', 'value']) + ['valid_from' => $year.'-01-01', 'valid_to' => $year.'-12-31'];
                if ($target = $existing->get(mb_strtolower($source->name))) {
                    $target->update($data);
                    $updated++;
                } else {
                    PlanningBaseLoad::query()->withoutGlobalScope('tenant')->create($data + ['tenant_id' => $targetTenantId, 'year' => $year, 'name' => $source->name]);
                    $created++;
                }
            }
            $report->add($this->label(), (string) $sourceYear, __(':created angelegt, :updated angeglichen (Jahr :year)', ['created' => $created, 'updated' => $updated, 'year' => $year]));
        }
    }

    private function targetYear(): int
    {
        return (int) now()->year;
    }

    /** @return Collection<int, PlanningBaseLoad> */
    private function rows(int $tenantId): Collection
    {
        return PlanningBaseLoad::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('year')->orderBy('id')->get();
    }
}
