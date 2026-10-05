<?php

/**
 * Wer die Hauptseite (nicht die Hilfeseite) sehen bzw. nutzen kann - nur eine Erläuterung für den Super-Admin im Hilfe-Panel
 * (Ralf, 2026-10-05). Die tatsächliche Prüfung steht im Code; hier ist sie nur beschrieben und muss bei Änderungen der Rechte
 * mitgepflegt werden. Schlüssel wie in der Hilfeverwaltung bei "Seiten (Routennamen)": Reiter-Schlüssel (D-1234#reiter), Dialog-ID
 * oder Routenname; der genaueste Eintrag gewinnt. Für Admin-Seiten (can:access-…) wird ohne Eintrag automatisch beschrieben.
 */
return [
    // Dialog "Zeiterfassung" am Projekt (D-78FH)
    'D-78FH' => 'Alle, die das Projekt öffnen können. Gebucht wird nur, wenn dem Benutzer eine Person zugeordnet ist.',
    'D-78FH#buchungen' => 'Alle, die das Projekt öffnen können und denen eine Person zugeordnet ist. Jede Person sieht und löscht nur ihre eigenen Buchungen, auch Administratoren.',
    'D-78FH#jobs' => 'Mit dem Recht „Jobs am Projekt verwalten“ (project.jobload.manage); Administratoren immer.',
    'D-78FH#aufteilung' => 'Mit dem Recht „Jobs am Projekt verwalten“ (project.jobload.manage); Administratoren immer. Nur bei einem Hauptprojekt mit Unterprojekten.',

    // Projektdetails (D-MFQU), Reiter Planung und Zeiten
    'D-MFQU#planung.planstunden' => 'Alle, die das Projekt sehen. Ändern nur mit dem Planungsrecht (planning.view); Administratoren immer.',
    'D-MFQU#planung.auslastung' => 'Mit den Rechten „Projekte ansehen“ und „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer.',
    'D-MFQU#planung.terminplan' => 'Alle, die das Projekt sehen.',
    'D-MFQU#zeiten.uebersicht' => 'Alle, die das Projekt sehen (Summen ohne Personenbezug).',
    'D-MFQU#zeiten.personen' => 'Mit den Rechten „Projekte ansehen“ und „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer.',
    'D-MFQU#zeiten.gesamt' => 'Alle, die das Projekt sehen. Die Aufschlüsselung nach Personen oder Jobs nur mit dem Planungsrecht (planning.view).',
];
