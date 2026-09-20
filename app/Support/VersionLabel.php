<?php

namespace App\Support;

/**
 * "Kundenversion" (Ralf, 2026-09-20): die Version, wie der KUNDE sie nennt -
 * Freitext ("V3", "1.2", "Rev. 04", "2024-03", "B" ...), nicht mehr nur eine
 * ganze Zahl. Die Reihenfolge einer Versionskette hängt nicht daran (die
 * kommt aus der Stamm-Position), hier geht es nur um das Hochzählen beim
 * Aufversionieren. (Die Sortierung der Spalte übernimmt die generierte
 * Sortierspalte, siehe AttributeColumnManager.)
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
}
