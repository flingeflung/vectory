<?php

namespace App\Services\PresetCopy;

use App\Models\Market;
use Illuminate\Support\Collection;

/**
 * Märkte: Identität = Land (ISO) + Sprachcode. Gleicher Markt im Ziel = Konflikt; "Überschreiben"
 * gleicht nur Namen und "keine Übersetzung" an (die ID bleibt, Verweise bleiben gültig).
 */
class MarketsArea implements PresetArea
{
    public function key(): string
    {
        return 'markets';
    }

    public function label(): string
    {
        return __('Märkte');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->all($targetTenantId)->keyBy(fn (Market $m) => $this->identity($m));

        return Market::sortedForDisplay($this->all($sourceTenantId))->map(fn (Market $m) => [
            'key' => $this->identity($m),
            'label' => $m->label(),
            'parent' => null,
            'conflict' => $existing->has($this->identity($m)),
            'renamable' => false,
            'note' => null,
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $existing = $this->all($targetTenantId)->keyBy(fn (Market $m) => $this->identity($m));

        foreach ($this->all($sourceTenantId) as $source) {
            $key = $this->identity($source);
            if (! isset($choices[$key])) {
                continue;
            }
            $data = $source->only(['country_id', 'language_id', 'country_iso', 'country_name', 'country_short_name', 'language_code', 'language_name', 'no_translation']);

            if ($target = $existing->get($key)) {
                if ($choices[$key] === 'overwrite') {
                    $target->update($data);
                    $report->add($this->label(), $source->label(), __('überschrieben'));
                } else {
                    $report->add($this->label(), $source->label(), __('übersprungen (gibt es schon)'));
                }

                continue;
            }

            Market::query()->create($data + ['tenant_id' => $targetTenantId, 'sort' => 0]);
            $report->add($this->label(), $source->label(), __('angelegt'));
        }

        Market::renumberForTenant($targetTenantId);
    }

    /** @return Collection<int, Market> */
    private function all(int $tenantId): Collection
    {
        return Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->get();
    }

    public static function identityOf(Market $market): string
    {
        return $market->country_iso.'|'.strtolower($market->language_code);
    }

    private function identity(Market $market): string
    {
        return $market->country_iso.'|'.strtolower($market->language_code);
    }
}
