<?php

/**
 * Reiter von Dialogen, zu denen es eigene Hilfeseiten geben kann (Ralf, 2026-10-04): Das Fragezeichen bleibt eines
 * je Dialog und zeigt die Hilfe des gerade sichtbaren Reiters. Im Dialog markiert data-help-tab="<schlüssel>" den
 * Reiter-Inhalt; die Hilfeseite trägt bei "Seiten (Routennamen)" "<Dialog-ID>#<schlüssel>" ein, z.B. D-MFQU#planung.auslastung.
 * Gibt es zum Reiter keine eigene Seite, gilt die des übergeordneten Reiters ("planung"), dann die des Dialogs.
 * Ein Test (HelpTabsTest) hält diese Liste und die Markierungen im Quelltext deckungsgleich.
 *
 * Dialogname (siehe config/dialog-ids.php) => Reiter-Schlüssel => Bezeichnung
 */
return [
    'project-overlay' => [
        'details' => 'Details',
        'vorgaenge' => 'Vorgänge',
        'workflow' => 'Workflow',
        'planung' => 'Planung',
        'planung.planstunden' => 'Planung › Planstunden',
        'planung.auslastung' => 'Planung › Auslastung',
        'planung.terminplan' => 'Planung › Terminplan',
        'zeiten' => 'Zeiten',
        'zeiten.uebersicht' => 'Zeiten › Projektstunden',
        'zeiten.personen' => 'Zeiten › Personen & Tage',
        'zeiten.gesamt' => 'Zeiten › Zeitverlauf',
    ],
];
