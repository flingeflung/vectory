<?php

namespace App\Support;

/**
 * Kurze, stabile Kennung jedes Dialogs/Overlays (Ralf, 2026-10-03): steht klein oben links im
 * Dialog, damit sich Fehler ("Such die ID oben links und nenne sie mir") und Hilfeseiten ohne
 * lange Beschreibung einem Dialog zuordnen lassen. Berechnet aus dem Dialognamen
 * (<x-modal name="…">), eine Pflege-Tabelle gibt es nicht. Ein angehängter Datensatz-Zähler
 * ("projektgruppen-panel-123") wird entfernt, damit alle Instanzen eines Dialogtyps dieselbe ID
 * tragen. Hilfeartikel ordnen sich über diese ID zu (HelpArticle::route_names).
 */
final class DialogId
{
    public static function for(string $modalName): string
    {
        $normalized = preg_replace('/-\d+$/', '', $modalName);
        $code = strtoupper(substr(base_convert(sprintf('%u', crc32($normalized)), 10, 36), -4));

        return 'D-'.str_pad($code, 4, '0', STR_PAD_LEFT);
    }
}
