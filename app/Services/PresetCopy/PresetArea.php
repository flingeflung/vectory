<?php

namespace App\Services\PresetCopy;

/**
 * Ein kopierbarer Bereich der zentralen Seite "Voreinstellungen übernehmen" (Ralf, 2026-10-03):
 * liefert die einzeln wählbaren Einträge des Quellkunden samt Konflikt-Status gegenüber dem
 * Zielkunden und übernimmt die gewählten. Neue Bereiche (Attribute, Workflows, ...) werden nur
 * in PresetCopier::areas() eingehängt, die Seite selbst bleibt unverändert.
 */
interface PresetArea
{
    public function key(): string;

    public function label(): string;

    /**
     * Reihenfolge = Anzeigereihenfolge; "parent" ist der key des übergeordneten Eintrags
     * (der bei Wahl des Untereintrags automatisch mit übernommen wird).
     *
     * @return list<array{key: string, label: string, parent: ?string, conflict: bool, renamable: bool, note: ?string, group?: string, group_hint?: string, hint?: string}>
     *                                                                                                                  Optional: "group" (+ "group_hint") setzt eine Zwischenüberschrift vor den Eintrag, "hint" ist ein Tooltip.
     */
    public function items(int $sourceTenantId, int $targetTenantId): array;

    /**
     * @param  array<string, string>  $choices  Eintrags-key => 'copy'|'overwrite'|'rename' (nur gewählte Einträge)
     */
    public function apply(int $sourceTenantId, int $targetTenantId, array $choices, PresetReport $report): void;
}
