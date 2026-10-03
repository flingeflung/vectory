<?php

namespace App\Services\PresetCopy;

use App\Models\PermissionTemplate;
use Illuminate\Support\Collection;

/**
 * Rechte-Sets und Bausteine (Identität = Art + Name). Kopiert werden Rechte, eingebundene Bausteine und die
 * lebende Basis - nie die Zuordnung von Personen. Es gibt hier bewusst KEIN Überschreiben (Ralf, 2026-10-03):
 * sonst würden sich die Rechte schon zugeordneter Personen im Ziel stillschweigend ändern. Wer ein vorhandenes
 * Set trotzdem übernehmen will, wählt "Umbenennen". Basis und Bausteine eines Sets werden bei Bedarf als
 * Voraussetzung mit angelegt; gibt es dort schon ein gleichnamiges, wird das vorhandene verwendet (und im Bericht genannt).
 */
class PermissionTemplatesArea implements PresetArea
{
    use NamesCopies;

    public function key(): string
    {
        return 'permission-templates';
    }

    public function label(): string
    {
        return __('Rechte-Sets');
    }

    public function hint(): string
    {
        return __('Rechte-Sets werden nie überschrieben, weil sich sonst die Rechte bereits zugeordneter Personen ändern würden. Möchten Sie ein vorhandenes Set trotzdem übernehmen, wählen Sie „Umbenennen“. Zuordnungen von Personen werden nie mitkopiert.');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->templates($targetTenantId)->map(fn (PermissionTemplate $t) => $this->identity($t))->flip();
        $items = [];

        foreach ([false => __('Rechte-Sets'), true => __('Bausteine (in mehrere Sets einbindbar)')] as $isBaustein => $group) {
            foreach ($this->templates($sourceTenantId)->where('is_baustein', (bool) $isBaustein) as $template) {
                $items[] = [
                    'key' => 'p:'.$template->id,
                    'label' => $template->name,
                    'group' => $group,
                    'parent' => null,
                    'conflict' => $existing->has($this->identity($template)),
                    'renamable' => true,
                    'overwritable' => false,
                    'note' => $template->permissions->count().' '.__('Rechte')
                        .($template->basis ? ' · '.__('Basis: :name', ['name' => $template->basis->name]) : '')
                        .($template->bausteine->isNotEmpty() ? ' · '.__('Bausteine: :names', ['names' => $template->bausteine->pluck('name')->implode(', ')]) : ''),
                ];
            }
        }

        return $items;
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $sources = $this->templates($sourceTenantId)->keyBy('id');
        $map = []; // Quell-ID => Ziel-ID

        $ensure = function (PermissionTemplate $source, ?string $choice) use (&$ensure, &$map, $sources, $targetTenantId, $report): int {
            $explicit = $choice !== null;
            $rename = $choice === 'rename';
            if (isset($map[$source->id]) && ! $rename) {
                return $map[$source->id];
            }

            $targets = $this->templates($targetTenantId);
            $existing = $targets->first(fn (PermissionTemplate $t) => $this->identity($t) === $this->identity($source));

            if ($existing && ! $rename) {
                $report->add($this->label(), $source->name, $explicit
                    ? __('übersprungen (gibt es schon)')
                    : __('vorhanden, wird als Voraussetzung verwendet'));

                return $map[$source->id] = $existing->id;
            }

            $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
            $new = PermissionTemplate::query()->withoutGlobalScope('tenant')->create([
                'tenant_id' => $targetTenantId, 'name' => $name, 'is_baustein' => $source->is_baustein,
                'sort' => 1 + (int) $targets->max('sort'),
            ]);
            $new->permissions()->sync($source->permissions->pluck('id')->all());
            $map[$source->id] = $new->id;

            if ($source->basis_id && ($basis = $sources->get($source->basis_id))) {
                $new->update(['basis_id' => $ensure($basis, null)]);
            }
            $new->bausteine()->sync(
                $source->bausteine->map(fn (PermissionTemplate $b) => $ensure($sources->get($b->id), null))->all()
            );

            $report->add($this->label(), $source->name, $existing
                ? __('angelegt als „:name“', ['name' => $name])
                : ($explicit ? __('angelegt') : __('angelegt (als Voraussetzung)')));

            return $new->id;
        };

        foreach ($sources as $source) {
            $choice = $choices['p:'.$source->id] ?? null;
            if ($choice !== null) {
                // Überschreiben gibt es nicht: alles außer "Umbenennen" verhält sich wie "Überspringen".
                $ensure($source, $choice === 'rename' ? 'rename' : 'copy');
            }
        }
    }

    private function identity(PermissionTemplate $template): string
    {
        return ($template->is_baustein ? 'b' : 's').'|'.mb_strtolower($template->name);
    }

    /** @return Collection<int, PermissionTemplate> */
    private function templates(int $tenantId): Collection
    {
        return PermissionTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->with(['permissions', 'basis', 'bausteine'])->orderBy('sort')->orderBy('id')->get();
    }
}
