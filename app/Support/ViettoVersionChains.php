<?php

namespace App\Support;

/**
 * Viettos Versionsketten (Ralf, 2026-09-20): alle Projekte mit derselben
 * Mat.-Nr. (Print/Video) bzw. derselben ODN (Online) bilden eine Kette,
 * Reihenfolge nach Projektnummer. Daraus entstehen Stamm-ID und Position.
 *
 * Schlüsselwahl: Online-Dokumente (intPublikationstyp 1) bevorzugen die ODN,
 * alle anderen die Mat.-Nr.; fehlt der bevorzugte Wert, gilt der andere.
 * Wichtig: in Vietto ist intPublikationstyp bei den meisten Projekten "-1"
 * (nicht gesetzt), Mat.-Nr./ODN stehen aber trotzdem - eine reine Auswertung
 * nach Publikationstyp würde den Großteil der Ketten übersehen. (Vietto
 * selbst zeigt den Versionen-Button nur bei gesetztem Typ, die DATEN geben
 * mehr her.)
 */
class ViettoVersionChains
{
    /**
     * @param  iterable<object>  $rows  Vietto-Zeilen mit pn, strMatnr, strODN, intPublikationstyp, nach pn sortiert
     * @return array<string, array{stamm_id: string, stamm_position: int}> Projektnummer => Kette
     */
    public static function build(iterable $rows, int $tenantId): array
    {
        $map = [];
        $positions = [];

        foreach ($rows as $row) {
            $matnr = trim((string) ($row->strMatnr ?? ''));
            $odn = trim((string) ($row->strODN ?? ''));
            $online = (int) ($row->intPublikationstyp ?? 0) === 1;

            $key = null;
            if ($online) {
                $key = $odn !== '' ? 'odn:'.$odn : ($matnr !== '' ? 'matnr:'.$matnr : null);
            } else {
                $key = $matnr !== '' ? 'matnr:'.$matnr : ($odn !== '' ? 'odn:'.$odn : null);
            }

            if ($key === null) {
                continue;
            }

            $positions[$key] = ($positions[$key] ?? 0) + 1;
            $map[$row->pn] = [
                'stamm_id' => StammId::fromSeed($tenantId.':'.$key),
                'stamm_position' => $positions[$key],
            ];
        }

        return $map;
    }
}
