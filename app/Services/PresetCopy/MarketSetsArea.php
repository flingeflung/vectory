<?php

namespace App\Services\PresetCopy;

use App\Models\Market;
use App\Models\MarketSet;
use Illuminate\Support\Collection;

/**
 * Ländergruppen (Märkte-Sets): Identität = Name. Die enthaltenen Märkte werden bei Bedarf im Ziel mit
 * angelegt (ohne Märkte wäre die Gruppe leer). "Überschreiben" setzt die Mitglieder der Zielgruppe auf
 * die der Quelle - die Gruppe behält ihre ID, Projekte bleiben unberührt.
 */
class MarketSetsArea implements PresetArea
{
    public function key(): string
    {
        return 'market-sets';
    }

    public function label(): string
    {
        return __('Ländergruppen');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->sets($targetTenantId)->pluck('name');

        return $this->sets($sourceTenantId)->map(fn (MarketSet $set) => [
            'key' => 'g:'.$set->id,
            'label' => $set->name,
            'parent' => null,
            'conflict' => $existing->contains($set->name),
            'renamable' => true,
            'note' => $set->markets->count().' '.__('Märkte'),
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $targetMarkets = $this->markets($targetTenantId)->keyBy(fn (Market $m) => MarketsArea::identityOf($m));
        $created = false;

        foreach ($this->sets($sourceTenantId) as $source) {
            $choice = $choices['g:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targetSets = $this->sets($targetTenantId);
            $existing = $targetSets->firstWhere('name', $source->name);

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));

                continue;
            }

            // Mitglieder: fehlende Märkte im Ziel anlegen.
            $memberIds = [];
            foreach ($source->markets as $market) {
                $identity = MarketsArea::identityOf($market);
                if (! $targetMarkets->has($identity)) {
                    $targetMarkets->put($identity, Market::query()->withoutGlobalScope('tenant')->create(
                        $market->only(['country_id', 'language_id', 'country_iso', 'country_name', 'country_short_name', 'language_code', 'language_name', 'no_translation'])
                        + ['tenant_id' => $targetTenantId, 'sort' => 0]
                    ));
                    $created = true;
                    $report->add(__('Märkte'), $market->label(), __('angelegt (für die Ländergruppe „:name“)', ['name' => $source->name]));
                }
                $memberIds[] = $targetMarkets->get($identity)->id;
            }

            if ($existing && $choice === 'overwrite') {
                $set = $existing;
                $outcome = __('überschrieben');
            } else {
                $name = $existing ? $this->freeName($source->name, $targetSets->pluck('name')->all()) : $source->name;
                $set = MarketSet::query()->withoutGlobalScope('tenant')->create([
                    'tenant_id' => $targetTenantId,
                    'name' => $name,
                    'sort' => (int) $targetSets->max('sort') + 1,
                ]);
                $outcome = $existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt');
            }

            $set->markets()->sync(collect($memberIds)->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $targetTenantId]])->all());
            $report->add($this->label(), $source->name, $outcome);
        }

        if ($created) {
            Market::renumberForTenant($targetTenantId);
        }
    }

    private function freeName(string $name, array $taken): string
    {
        $candidate = $name.' ('.__('Kopie').')';
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = $name.' ('.__('Kopie').' '.$i.')';
        }

        return $candidate;
    }

    /** @return Collection<int, MarketSet> */
    private function sets(int $tenantId): Collection
    {
        return MarketSet::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->with(['markets' => fn ($q) => $q->withoutGlobalScope('tenant')])->orderBy('sort')->orderBy('id')->get();
    }

    /** @return Collection<int, Market> */
    private function markets(int $tenantId): Collection
    {
        return Market::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->get();
    }
}
