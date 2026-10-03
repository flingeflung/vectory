<?php

namespace App\Services\PresetCopy;

use App\Models\Attribute;
use App\Models\CopyTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Projektkopie-Vorlagen: legen fest, welche Felder beim Kopieren eines Projekts übernommen werden (Identität = Name).
 * Die Felder werden über ihren technischen Schlüssel ins Ziel übersetzt; Felder, die es dort nicht gibt, werden
 * ausgelassen und im Bericht genannt (vorher die Projektattribute übernehmen). Auf eine Vorlage verweist nichts,
 * Überschreiben ist daher immer möglich.
 */
class CopyTemplatesArea implements PresetArea
{
    use NamesCopies;

    public function key(): string
    {
        return 'copy-templates';
    }

    public function label(): string
    {
        return __('Projektkopie-Vorlagen');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->templates($targetTenantId)->pluck('name');

        return $this->templates($sourceTenantId)->map(fn (CopyTemplate $template) => [
            'key' => 'k:'.$template->id,
            'label' => $template->name,
            'parent' => null,
            'conflict' => $existing->contains($template->name),
            'renamable' => true,
            'note' => $this->fieldKeys($template)->count().' '.__('Felder'),
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $targetAttributes = Attribute::query()->withoutGlobalScope('tenant')->where('tenant_id', $targetTenantId)->pluck('id', 'key');

        foreach ($this->templates($sourceTenantId) as $source) {
            $choice = $choices['k:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targets = $this->templates($targetTenantId);
            $existing = $targets->firstWhere('name', $source->name);

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));

                continue;
            }

            if ($existing && $choice === 'overwrite') {
                $target = $existing;
                $outcome = __('überschrieben');
            } else {
                $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
                $target = CopyTemplate::query()->withoutGlobalScope('tenant')->create([
                    'tenant_id' => $targetTenantId, 'name' => $name, 'sort' => (int) $targets->max('sort') + 1,
                ]);
                $outcome = $existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt');
            }

            $keys = $this->fieldKeys($source);
            $missing = $keys->reject(fn ($key) => $targetAttributes->has($key))->values();
            DB::table('copy_template_attribute')->where('copy_template_id', $target->id)->delete();
            foreach ($keys->filter(fn ($key) => $targetAttributes->has($key)) as $key) {
                DB::table('copy_template_attribute')->insert([
                    'copy_template_id' => $target->id, 'attribute_id' => $targetAttributes[$key], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $report->add($this->label(), $source->name, $outcome.($missing->isEmpty() ? '' : ' – '.__('Felder im Ziel nicht vorhanden, ausgelassen: :names (zuerst die Projektattribute übernehmen)', ['names' => $missing->implode(', ')])));
        }
    }

    /** @return Collection<int, string> */
    private function fieldKeys(CopyTemplate $template): Collection
    {
        return $template->fields()->withoutGlobalScope('tenant')->pluck('attributes.key');
    }

    /** @return Collection<int, CopyTemplate> */
    private function templates(int $tenantId): Collection
    {
        return CopyTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('id')->get();
    }
}
