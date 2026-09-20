<?php

namespace App\Support;

/**
 * "Kundenversion" (Ralf, 2026-09-20): die Version, wie der KUNDE sie nennt -
 * Freitext ("V3", "1.2", "Rev. 04", "2024-03", "B" ...), nicht mehr nur eine
 * ganze Zahl. Die Reihenfolge einer Versionskette hängt nicht daran (die
 * kommt aus der Stamm-Position), hier geht es nur um Hochzählen beim
 * Aufversionieren und um eine sinnvolle Sortierung der Spalte.
 */
class VersionLabel
{
    /**
     * Beim Aufversionieren: die LETZTE Zahl im Text um 1 erhöhen, alles andere
     * (Präfix, Trenner, führende Nullen) bleibt - "V0015" -> "V0016",
     * "Rev. 04" -> "Rev. 05", "1.2" -> "1.3". Enthält der Text keine Zahl
     * (z.B. "B") oder ist er leer, ergibt sich kein Vorschlag (null) - dann
     * trägt der Bearbeiter die neue Kundenversion von Hand ein.
     */
    public static function increment(?string $version): ?string
    {
        $version = trim((string) $version);

        if ($version === '' || ! preg_match('/(\d+)(?!.*\d)/', $version, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        [$digits, $offset] = $match[1];
        $next = str_pad((string) ((int) $digits + 1), strlen($digits), '0', STR_PAD_LEFT);

        return substr($version, 0, $offset).$next.substr($version, $offset + strlen($digits));
    }

    /**
     * Zahlenschlüssel fürs Sortieren (Spalte projects.version_sort): bis zu drei
     * Zahlengruppen des Textes, jede auf 0..9999 begrenzt, als a*1e8 + b*1e4 + c.
     * "V9" < "V10", "1.2" < "1.10", "2024-03" < "2024-04"; ohne Zahl null
     * (bei aufsteigender Sortierung vorn, wie jedes leere Feld).
     */
    public static function sortKey(?string $version): ?int
    {
        if ($version === null || ! preg_match_all('/\d+/', $version, $matches)) {
            return null;
        }

        $groups = array_slice($matches[0], 0, 3);
        $key = 0;
        foreach ([100_000_000, 10_000, 1] as $i => $weight) {
            $key += min((int) ($groups[$i] ?? 0), 9999) * $weight;
        }

        return $key;
    }
}
