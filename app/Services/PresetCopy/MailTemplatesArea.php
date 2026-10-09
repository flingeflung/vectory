<?php

namespace App\Services\PresetCopy;

use App\Models\MailTemplate;
use Illuminate\Support\Collection;

/**
 * Mail-Vorlagen (Identität = Name). Auf eine Vorlage verweist nichts, Überschreiben ist daher immer möglich.
 * Platzhalter wie {material_number} werden unverändert übernommen - fehlt das zugehörige Attribut im Ziel, bleibt
 * der Platzhalter dort leer; die Attribute bitte mit übernehmen.
 */
class MailTemplatesArea implements PresetArea
{
    use NamesCopies;

    public function key(): string
    {
        return 'mail-templates';
    }

    public function label(): string
    {
        return __('Mail-Vorlagen');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $existing = $this->templates($targetTenantId)->pluck('name');

        return $this->templates($sourceTenantId)->map(fn (MailTemplate $template) => [
            'key' => 'm:'.$template->id,
            'label' => $template->name,
            'parent' => null,
            'conflict' => $existing->contains($template->name),
            'renamable' => true,
            'note' => $template->subject,
        ])->all();
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        foreach ($this->templates($sourceTenantId) as $source) {
            $choice = $choices['m:'.$source->id] ?? null;
            if ($choice === null) {
                continue;
            }
            $targets = $this->templates($targetTenantId);
            $existing = $targets->firstWhere('name', $source->name);
            $data = $source->only(['description', 'subject', 'body']);

            if ($existing && $choice === 'copy') {
                $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));
            } elseif ($existing && $choice === 'overwrite') {
                $existing->update($data);
                $report->add($this->label(), $source->name, __('überschrieben'));
            } else {
                $name = $existing ? $this->freeName($source->name, $targets->pluck('name')->all()) : $source->name;
                MailTemplate::query()->withoutGlobalScope('tenant')->create($data + ['tenant_id' => $targetTenantId, 'name' => $name]);
                $report->add($this->label(), $source->name, $existing ? __('angelegt als „:name“', ['name' => $name]) : __('angelegt'));
            }
        }
    }

    /** @return Collection<int, MailTemplate> */
    private function templates(int $tenantId): Collection
    {
        return MailTemplate::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->orderBy('name')->get();
    }
}
