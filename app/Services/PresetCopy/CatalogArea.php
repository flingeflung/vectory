<?php

namespace App\Services\PresetCopy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Einfache Stammdaten-Kataloge ohne Abhängigkeiten (Dienstleister, Abteilungen, Geschäftsbereiche, Rollen):
 * Identität = Name. Überschreiben gleicht Kürzel und "aktiv" an, Umbenennen legt "Name (Kopie)" an. Personen
 * gehören zur Organisation und werden nie mitgenommen - es geht nur um die Auswahlkataloge.
 */
abstract class CatalogArea implements PresetArea
{
    use NamesCopies;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** @return list<string> Spalten, die zusätzlich zum Namen kopiert werden (z.B. short_name, active) */
    abstract protected function copiedColumns(): array;

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->rows($targetTenantId)->map(fn (Model $m) => mb_strtolower($m->name));

        return $this->rows($sourceTenantId)->map(fn (Model $row) => [
            'key' => 'r:'.$row->id,
            'label' => $row->name.($row->active === false ? ' [i]' : ''),
            'parent' => null,
            'conflict' => $existing->contains(mb_strtolower($row->name)),
            'renamable' => true,
            'note' => $row->short_name ?? null,
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $model = $this->modelClass();

        foreach ($this->rows($sourceTenantId) as $source) {
            $choice = $choices['r:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targets = $this->rows($targetTenantId);
            $existing = $targets->first(fn (Model $m) => mb_strtolower($m->name) === mb_strtolower($source->name));
            $data = $source->only($this->copiedColumns());

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));
            } elseif ($existing && $choice === 'overwrite') {
                $existing->update($data);
                $report->add($this->label(), $source->name, __('überschrieben'));
            } else {
                $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
                $model::query()->withoutGlobalScope('tenant')->create($data + [
                    'tenant_id' => $targetTenantId, 'name' => $name, 'sort' => 1 + (int) $targets->max('sort'),
                ]);
                $report->add($this->label(), $source->name, $existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt'));
            }
        }
    }

    /** @return Collection<int, Model> */
    private function rows(int $tenantId): Collection
    {
        $model = $this->modelClass();

        return $model::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get();
    }
}
