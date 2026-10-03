<?php

namespace App\Services\PresetCopy;

use App\Models\Checklist;
use App\Models\ChecklistPoint;
use App\Models\ChecklistSection;
use App\Models\ProjectChecklist;
use Illuminate\Support\Collection;

/**
 * Checklisten mit Abschnitten und Punkten (Identität = Name). Überschreiben baut Abschnitte und Punkte neu auf -
 * deshalb nur, solange im Ziel kein Projekt die Checkliste verwendet (Projekt-Haken hängen an den Punkten).
 */
class ChecklistsArea implements PresetArea
{
    use NamesCopies;

    public function key(): string
    {
        return 'checklists';
    }

    public function label(): string
    {
        return __('Checklisten');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->checklists($targetTenantId)->keyBy('name');

        return $this->checklists($sourceTenantId)->map(function (Checklist $checklist) use ($existing) {
            $target = $existing->get($checklist->name);
            $used = $target && $this->isUsed($target);

            return [
                'key' => 'c:'.$checklist->id,
                'label' => $checklist->name,
                'parent' => null,
                'conflict' => $target !== null,
                'renamable' => true,
                'overwritable' => ! $used,
                'note' => $checklist->pointsCount().' '.__('Punkte').($used ? ' · '.__('im Ziel in Projekten verwendet - Überschreiben nicht möglich') : ''),
            ];
        })->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        foreach ($this->checklists($sourceTenantId) as $source) {
            $choice = $choices['c:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targets = $this->checklists($targetTenantId);
            $existing = $targets->firstWhere('name', $source->name);

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));
            } elseif ($existing && $choice === 'overwrite') {
                if ($this->isUsed($existing)) {
                    $report->add($this->label(), $source->name, __('übersprungen (im Ziel in Projekten verwendet)'));

                    continue;
                }
                $existing->sections->each(fn (ChecklistSection $section) => $section->delete());
                $existing->update(['active' => $source->active]);
                $this->copyContent($source, $existing);
                $report->add($this->label(), $source->name, __('überschrieben'));
            } else {
                $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
                $new = Checklist::query()->withoutGlobalScope('tenant')->create([
                    'tenant_id' => $targetTenantId, 'name' => $name, 'active' => $source->active,
                    'sort' => (int) $targets->max('sort') + 1,
                ]);
                $this->copyContent($source, $new);
                $report->add($this->label(), $source->name, $existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt'));
            }
        }
    }

    private function copyContent(Checklist $source, Checklist $target): void
    {
        foreach ($source->sections as $section) {
            $newSection = ChecklistSection::query()->create(['checklist_id' => $target->id, 'title' => $section->title, 'sort' => $section->sort]);
            foreach ($section->points as $point) {
                ChecklistPoint::query()->create(['checklist_section_id' => $newSection->id, 'title' => $point->title, 'sort' => $point->sort]);
            }
        }
    }

    private function isUsed(Checklist $checklist): bool
    {
        return ProjectChecklist::query()->withoutGlobalScopes()->where('checklist_id', $checklist->id)->exists();
    }

    /** @return Collection<int, Checklist> */
    private function checklists(int $tenantId): Collection
    {
        return Checklist::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->with('sections.points')->orderBy('sort')->orderBy('id')->get();
    }
}
