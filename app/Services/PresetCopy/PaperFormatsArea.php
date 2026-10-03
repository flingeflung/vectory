<?php

namespace App\Services\PresetCopy;

use App\Models\PaperFormat;
use App\Models\PaperFormatCombination;
use Illuminate\Support\Collection;

/**
 * Papierformate (Identität = Name, nur aktive) und freigegebene Format-Kombinationen (Identität = Ausgangs- plus
 * Endformat). Eine Kombination zieht ihre beiden Formate bei Bedarf mit. Überschreiben gleicht Maße, Kürzel,
 * Bemerkung bzw. Falzanzahl an - die ID bleibt, bestehende Projekte sind nicht berührt.
 */
class PaperFormatsArea implements PresetArea
{
    public function key(): string
    {
        return 'paper-formats';
    }

    public function label(): string
    {
        return __('Papierformate');
    }

    public function items(int $sourceTenantId, int $targetTenantId): array
    {
        $targetFormats = $this->formats($targetTenantId)->keyBy(fn (PaperFormat $f) => mb_strtolower($f->name));
        $sourceFormats = $this->formats($sourceTenantId)->keyBy('id');
        $targetPairs = $this->combinations($targetTenantId)->map(fn ($c) => $c->input_format_id.':'.$c->output_format_id)->flip();
        $items = [];

        foreach ($sourceFormats as $format) {
            $items[] = [
                'key' => 'f:'.$format->id, 'label' => $format->name, 'parent' => null,
                'conflict' => $targetFormats->has(mb_strtolower($format->name)), 'renamable' => false,
                'note' => $format->width_mm && $format->height_mm ? $format->width_mm.' × '.$format->height_mm.' mm' : null,
            ];
        }

        foreach ($this->combinations($sourceTenantId) as $combination) {
            $in = $sourceFormats->get($combination->input_format_id);
            $out = $sourceFormats->get($combination->output_format_id);
            if (! $in || ! $out) {
                continue;
            }
            $inTarget = $targetFormats->get(mb_strtolower($in->name));
            $outTarget = $targetFormats->get(mb_strtolower($out->name));
            $items[] = [
                'key' => 'p:'.$combination->id,
                'label' => $in->name.' → '.$out->name,
                'group' => __('Format-Kombinationen (Ausgangsformat → Endformat)'),
                'group_hint' => __('Eine Kombination bringt ihre beiden Formate bei Bedarf automatisch mit.'),
                'parent' => null,
                'conflict' => $inTarget && $outTarget && $targetPairs->has($inTarget->id.':'.$outTarget->id),
                'renamable' => false,
                'note' => $combination->fold_count ? $combination->fold_count.' '.__('Falz') : null,
            ];
        }

        return $items;
    }

    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void
    {
        $sourceFormats = $this->formats($sourceTenantId)->keyBy('id');
        $formatMap = []; // Quell-Format-ID => Ziel-Format-ID

        $ensure = function (PaperFormat $source, ?string $choice, bool $explicit) use (&$formatMap, $targetTenantId, $report): int {
            if (isset($formatMap[$source->id])) {
                return $formatMap[$source->id];
            }
            $targets = $this->formats($targetTenantId);
            $existing = $targets->first(fn (PaperFormat $f) => mb_strtolower($f->name) === mb_strtolower($source->name));
            $data = $source->only(['short_name', 'width_mm', 'height_mm', 'remark', 'show_dimensions']);

            if ($existing) {
                if ($explicit && $choice === 'overwrite') {
                    $existing->update($data);
                    $report->add($this->label(), $source->name, __('überschrieben'));
                } elseif ($explicit) {
                    $report->add($this->label(), $source->name, __('übersprungen (gibt es schon)'));
                }

                return $formatMap[$source->id] = $existing->id;
            }

            $new = PaperFormat::query()->withoutGlobalScope('tenant')->create($data + [
                'tenant_id' => $targetTenantId, 'name' => $source->name, 'active' => true, 'sort' => 1 + (int) $targets->max('sort'),
            ]);
            $report->add($this->label(), $source->name, $explicit ? __('angelegt') : __('angelegt (als Voraussetzung)'));

            return $formatMap[$source->id] = $new->id;
        };

        foreach ($sourceFormats as $format) {
            if (isset($choices['f:'.$format->id])) {
                $ensure($format, $choices['f:'.$format->id], true);
            }
        }

        foreach ($this->combinations($sourceTenantId) as $combination) {
            $choice = $choices['p:'.$combination->id] ?? null;
            $in = $sourceFormats->get($combination->input_format_id);
            $out = $sourceFormats->get($combination->output_format_id);
            if ($choice === null || ! $in || ! $out) {
                continue;
            }
            $inId = $ensure($in, null, false);
            $outId = $ensure($out, null, false);
            $label = $in->name.' → '.$out->name;
            $data = ['fold_count' => $combination->fold_count, 'remark' => $combination->remark];
            $existing = $this->combinations($targetTenantId)->first(fn ($c) => $c->input_format_id === $inId && $c->output_format_id === $outId);

            if ($existing) {
                if ($choice === 'overwrite') {
                    $existing->update($data + ['active' => true]);
                    $report->add(__('Format-Kombinationen'), $label, __('überschrieben'));
                } else {
                    $report->add(__('Format-Kombinationen'), $label, __('übersprungen (gibt es schon)'));
                }

                continue;
            }

            PaperFormatCombination::query()->withoutGlobalScope('tenant')->create($data + [
                'tenant_id' => $targetTenantId, 'input_format_id' => $inId, 'output_format_id' => $outId, 'active' => true,
            ]);
            $report->add(__('Format-Kombinationen'), $label, __('angelegt'));
        }
    }

    /** @return Collection<int, PaperFormat> Nur aktive */
    private function formats(int $tenantId): Collection
    {
        return PaperFormat::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('active', true)->orderBy('sort')->orderBy('id')->get();
    }

    /** @return Collection<int, PaperFormatCombination> Nur aktive */
    private function combinations(int $tenantId): Collection
    {
        return PaperFormatCombination::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->where('active', true)->orderBy('id')->get();
    }
}
