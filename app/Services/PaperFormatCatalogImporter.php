<?php

namespace App\Services;

use App\Models\PaperFormat;
use App\Models\PaperFormatCombination;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt den Papierformat-Katalog (Formate + Kombinationen) eines
 * anderen Kunden - Print-Formate-Feature Schritt 5, pull-basiert (Ralf:
 * "gut, machen wir so" zum Vorschlag "Stand im Zielkunden, Quelle wählen").
 *
 * Anders als TenantConfigCloner (nur in einen komplett leeren Kunden
 * kopierbar, einmalig bei Neuanlage) ist das hier bewusst beliebig oft
 * wiederholbar: bereits vorhandene Formate (gleicher Name) und
 * Kombinationen (gleiches Format-Paar) werden übersprungen statt
 * dupliziert, alles andere wird ergänzt. Nur aktive Formate/Kombinationen
 * werden übernommen - inaktive sind beim Quellkunden bewusst historisch
 * "in Rente".
 */
class PaperFormatCatalogImporter
{
    /**
     * @return array{formats_copied: int, formats_skipped: int, combinations_copied: int, combinations_skipped: int}
     */
    public function import(Tenant $source, Tenant $target): array
    {
        return DB::transaction(function () use ($source, $target) {
            [$formatMap, $formatsCopied, $formatsSkipped] = $this->importFormats($source, $target);
            [$combinationsCopied, $combinationsSkipped] = $this->importCombinations($source, $target, $formatMap);

            return [
                'formats_copied' => $formatsCopied,
                'formats_skipped' => $formatsSkipped,
                'combinations_copied' => $combinationsCopied,
                'combinations_skipped' => $combinationsSkipped,
            ];
        });
    }

    /**
     * @return array{0: array<int, int>, 1: int, 2: int} Alte Format-ID -> neue/bestehende Format-ID, Anzahl neu, Anzahl übersprungen
     */
    private function importFormats(Tenant $source, Tenant $target): array
    {
        $map = [];
        $copied = 0;
        $skipped = 0;

        $existingByName = PaperFormat::query()->withoutGlobalScope('tenant')->where('tenant_id', $target->id)->get()
            ->keyBy(fn (PaperFormat $f) => mb_strtolower($f->name));

        $nextSort = 1 + (int) PaperFormat::query()->withoutGlobalScope('tenant')->where('tenant_id', $target->id)->max('sort');

        PaperFormat::query()->withoutGlobalScope('tenant')->where('tenant_id', $source->id)->where('active', true)->orderBy('sort')->get()
            ->each(function (PaperFormat $row) use ($target, $existingByName, &$map, &$copied, &$skipped, &$nextSort) {
                $existing = $existingByName->get(mb_strtolower($row->name));

                if ($existing) {
                    $map[$row->id] = $existing->id;
                    $skipped++;

                    return;
                }

                $new = PaperFormat::query()->create([
                    'tenant_id' => $target->id,
                    'name' => $row->name,
                    'short_name' => $row->short_name,
                    'width_mm' => $row->width_mm,
                    'height_mm' => $row->height_mm,
                    'remark' => $row->remark,
                    'show_dimensions' => $row->show_dimensions,
                    'active' => true,
                    'sort' => $nextSort++,
                ]);
                $map[$row->id] = $new->id;
                $copied++;
            });

        return [$map, $copied, $skipped];
    }

    /**
     * @param  array<int, int>  $formatMap
     * @return array{0: int, 1: int} Anzahl neu, Anzahl übersprungen
     */
    private function importCombinations(Tenant $source, Tenant $target, array $formatMap): array
    {
        $copied = 0;
        $skipped = 0;

        $existingPairs = PaperFormatCombination::query()->withoutGlobalScope('tenant')->where('tenant_id', $target->id)
            ->get(['input_format_id', 'output_format_id'])
            ->map(fn (PaperFormatCombination $c) => $c->input_format_id.':'.$c->output_format_id)
            ->flip();

        PaperFormatCombination::query()->withoutGlobalScope('tenant')->where('tenant_id', $source->id)->where('active', true)->get()
            ->each(function (PaperFormatCombination $row) use ($target, $formatMap, &$existingPairs, &$copied, &$skipped) {
                // Sollte nicht vorkommen (eine aktive Kombination verweist
                // normalerweise auf aktive Formate), defensiv trotzdem
                // übersprungen statt eines Fremdschlüssel-Fehlers.
                if (! isset($formatMap[$row->input_format_id]) || ! isset($formatMap[$row->output_format_id])) {
                    $skipped++;

                    return;
                }

                $newInputId = $formatMap[$row->input_format_id];
                $newOutputId = $formatMap[$row->output_format_id];
                $key = $newInputId.':'.$newOutputId;

                if (isset($existingPairs[$key])) {
                    $skipped++;

                    return;
                }

                PaperFormatCombination::query()->create([
                    'tenant_id' => $target->id,
                    'input_format_id' => $newInputId,
                    'output_format_id' => $newOutputId,
                    'fold_count' => $row->fold_count,
                    'remark' => $row->remark,
                    'active' => true,
                ]);
                $existingPairs[$key] = true;
                $copied++;
            });

        return [$copied, $skipped];
    }
}
