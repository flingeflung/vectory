<?php

namespace App\Services;

use App\Models\Project;

/**
 * PN-Vergabe für "Projekt neu anlegen" - analog Viettos get_nextfree_pn()
 * (incl_functions.php ~2436), aber effizienter: EINE Abfrage aller im Jahr
 * bereits vergebenen PNs statt bis zu 9999 Einzelabfragen. Lücken-Auffüllung
 * bewusst BEIBEHALTEN (nicht "höchste+1") - Ralfs Begründung: Löschungen
 * passieren zeitnah nach Fehlanlage (Lücke bleibt klein), Lücken selbst sind
 * aus Audit-/Compliance-Sicht verdächtig.
 *
 * Prüft zusätzlich das Dateisystem (verwaiste Ordner ohne DB-Zeile) - eine
 * echte Vietto-Schwachstelle, die reine DB-Prüfung nicht abdeckt.
 *
 * Reine Berechnung, KEINE Reservierung/Sperre hier - die passiert erst beim
 * tatsächlichen Anlegen (ProjectController::store(), Tenant-Zeilen-Lock in
 * einer Transaktion), damit ein liegengelassener Anlegen-Dialog nichts
 * blockiert (gleiches Prinzip wie Viettos reine Live-Vorschau).
 */
class ProjectNumberAllocator
{
    public function __construct(private readonly ProjectDirectoryLocator $directoryLocator) {}

    public function nextFreePn(int $year, int $tenantId): string
    {
        $prefix = $this->yearPrefix($year);

        $usedNumbers = Project::query()
            ->where('tenant_id', $tenantId)
            ->where('source_pn', 'like', $prefix.'____')
            ->pluck('source_pn')
            ->map(fn (string $pn) => (int) substr($pn, 2, 4))
            ->all();

        $basePath = $this->directoryLocator->basePath($tenantId);
        if ($basePath !== null && is_dir($basePath)) {
            foreach (array_keys($this->directoryLocator->buildIndex($basePath)) as $pn) {
                if (str_starts_with($pn, $prefix)) {
                    $usedNumbers[] = (int) substr($pn, 2, 4);
                }
            }
        }

        $usedNumbers = array_unique($usedNumbers);
        sort($usedNumbers);

        $candidate = 1;
        foreach ($usedNumbers as $number) {
            if ($number < $candidate) {
                continue;
            }
            if ($number > $candidate) {
                break;
            }
            $candidate++;
        }

        return $prefix.str_pad((string) $candidate, 4, '0', STR_PAD_LEFT);
    }

    private function yearPrefix(int $year): string
    {
        return str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
    }
}
