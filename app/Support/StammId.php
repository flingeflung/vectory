<?php

namespace App\Support;

use App\Models\Project;

/**
 * "Stamm-ID" (Ralf, 2026-09-20): interner, aber SICHTBARER Schlüssel, den alle
 * Versionen desselben Dokuments gemeinsam tragen (Versionskette). Bewusst
 * KEINE fortlaufende Nummer (Verwechslungsgefahr mit der Projektnummer) und
 * nicht an die Kundennummer (Mat.-Nr./ODN/"V0015" ...) gekoppelt, weil deren
 * Format je Kunde völlig verschieden ist.
 *
 * Gespeichert wird die kanonische Form (12 Zeichen, Großbuchstaben+Ziffern,
 * ohne leicht verwechselbare Zeichen 0/O und 1/I/L). Angezeigt wird sie in
 * Dreiergruppen mit Bindestrich (XXXX-XXXX-XXXX), die Suche ignoriert
 * Bindestriche, Leerzeichen und Groß-/Kleinschreibung.
 */
class StammId
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 12;

    /** Zufällige, noch nicht vergebene Stamm-ID. */
    public static function generate(): string
    {
        do {
            $id = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $id .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (Project::query()->withoutGlobalScopes()->where('stamm_id', $id)->exists());

        return $id;
    }

    /**
     * Wiederholbar aus einem Text abgeleitete Stamm-ID (Vietto-Import: alle
     * Projekte mit derselben Mat.-Nr./ODN bekommen bei jedem Lauf dieselbe
     * ID, ein erneuter Import erzeugt keine Dubletten-Ketten).
     */
    public static function fromSeed(string $seed): string
    {
        $bytes = hash('sha256', 'stamm-id:'.$seed, true);
        $id = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $id .= self::ALPHABET[ord($bytes[$i]) % strlen(self::ALPHABET)];
        }

        return $id;
    }

    /** XXXX-XXXX-XXXX für die Anzeige. */
    public static function format(?string $id): string
    {
        return $id === null || $id === '' ? '' : implode('-', str_split($id, 4));
    }

    /** Sucheingabe auf die kanonische Form bringen (ohne Trenner, Großschreibung). */
    public static function normalize(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }
}
