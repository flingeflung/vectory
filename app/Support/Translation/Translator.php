<?php

namespace App\Support\Translation;

use Illuminate\Translation\Translator as BaseTranslator;

/**
 * Ralf-Bug-Report, 2026-09-18: trans_choice() zeigte fuer eine neue
 * Multichange-Zeile Englisch, obwohl Locale=de aktiv war und alle
 * benachbarten __()-Aufrufe in derselben Antwort korrekt Deutsch zeigten.
 *
 * Ursache: Illuminate\Translation\Translator::choice() ruft vorab
 * localeForChoice() auf, die per hasForLocale() prueft, ob fuer den
 * AKTIVEN Locale ein EXPLIZITER JSON-Eintrag existiert - falls nicht,
 * weicht sie direkt auf den Fallback-Locale (en) aus, ohne den Schluessel
 * je im aktiven Locale als Roh-/Quelltext zurueckzugeben. Vectory nutzt
 * aber durchgaengig den deutschen Quelltext selbst als Uebersetzungs-
 * schluessel (kein de.json-Eintrag noetig, siehe lang/de.json) - bei
 * trans_choice()-Schluesseln ohne de.json-Eintrag haelt Laravel Deutsch
 * deshalb faelschlich fuer "nicht verfuegbar", sobald zufaellig eine
 * en.json-Uebersetzung fuer denselben Schluessel existiert (wie bei
 * lang/en.json:8). __() hat diese Vorabpruefung nicht und funktioniert
 * deshalb immer korrekt - dieselbe Antwort zeigte deshalb ueberall sonst
 * Deutsch, nur bei dieser einen trans_choice()-Zeile Englisch.
 *
 * Fix: choice() direkt mit dem aktiven Locale ausfuehren, ohne die "hat
 * dieser Locale einen Eintrag"-Vorabpruefung - identisches Verhalten zu
 * __() (get() liefert bei fehlendem Eintrag ohnehin den Roh-Schluessel,
 * also den deutschen Quelltext, zurueck).
 */
class Translator extends BaseTranslator
{
    public function choice($key, $number, array $replace = [], $locale = null)
    {
        $locale = $locale ?: $this->getLocale();

        $line = $this->get($key, [], $locale);

        if (is_countable($number)) {
            $number = count($number);
        }

        if (! isset($replace['count'])) {
            $replace['count'] = $number;
        }

        return $this->makeReplacements(
            $this->getSelector()->choose($line, $number, $locale), $replace
        );
    }
}
