<?php

namespace App\Services\PresetCopy;

use Illuminate\Support\Facades\DB;

/**
 * Zentrale Übernahme von Voreinstellungen von einem Kunden zum anderen (Ralf, 2026-10-03, nach dem
 * Vorbild von InDesigns "Stile laden"): Einträge einzeln wählbar, Gleichnamiges wird je Eintrag
 * überschrieben, umbenannt oder übersprungen, alles in einer Transaktion (ganz oder gar nicht).
 */
class PresetCopier
{
    /** @return list<PresetArea> Reihenfolge = Reihenfolge der Übernahme (Abhängigkeiten zuerst). */
    public function areas(): array
    {
        return [
            new MarketSetsArea,
            new ProjectTypesArea,
            new AttributesArea,
            new WorkflowsArea,
            new ProjectTemplatesArea,
            new ChecklistsArea,
            new MailTemplatesArea,
            new CopyTemplatesArea,
            new PaperFormatsArea,
            new JobTypesArea,
            new HolidaysArea,
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $choices  Bereichs-key => (Eintrags-key => Aktion)
     */
    public function apply(int $sourceTenantId, int $targetTenantId, array $choices): PresetReport
    {
        abort_if($sourceTenantId === $targetTenantId, 422);
        $report = new PresetReport;

        DB::transaction(function () use ($sourceTenantId, $targetTenantId, $choices, $report) {
            foreach ($this->areas() as $area) {
                $selected = array_filter($choices[$area->key()] ?? [], fn ($action) => in_array($action, ['copy', 'overwrite', 'rename'], true));
                if ($selected !== []) {
                    $area->apply($sourceTenantId, $targetTenantId, $selected, $report);
                }
            }
        });

        $report->runDeferred();

        return $report;
    }
}
