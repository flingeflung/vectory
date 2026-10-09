<?php

/**
 * Wer die Hauptseite (nicht die Hilfeseite) sehen bzw. nutzen kann - nur eine Erläuterung für den Super-Admin im Hilfe-Panel
 * (Ralf, 2026-10-05). Die tatsächliche Prüfung steht im Code; hier ist sie nur beschrieben und muss bei Änderungen der Rechte
 * mitgepflegt werden. Schlüssel wie in der Hilfeverwaltung bei "Seiten (Routennamen)": Reiter-Schlüssel (D-1234#reiter), Dialog-ID
 * oder Routenname; der genaueste Eintrag gewinnt. Für Admin-Seiten (can:access-…) wird ohne Eintrag automatisch beschrieben. Rechte stehen als (schlüssel) im Text; ein Test prüft, dass es sie im Rechtekatalog gibt.
 */
return [
    // Hauptnavigation
    'dashboard' => 'Alle angemeldeten Benutzer.',
    'projekte' => 'Die Projektübersicht zeigt jedem Benutzer die Projekte, bei denen er als Projektbeteiligter eingetragen ist; alle Projekte sehen Benutzer mit dem Recht „Projekt: Details aufrufen“ (project.view), Administratoren oder – je Organisation einstellbar – jeder. Die Details eines Projekts öffnen nur Projektbeteiligte dieses Projekts, Benutzer mit dem Recht (project.view) und Administratoren.',
    'critical-projects.index' => 'Alle angemeldeten Benutzer. Normale Benutzer sehen nur Projekte, an denen sie als Person beteiligt sind. Administratoren und Benutzer mit dem Recht „Kritische Projekte: alle Projekte freigegebener Organisationen sehen“ (critical_projects.view_all) sehen alle.',
    'aufgaben' => 'Alle angemeldeten Benutzer. Standardmäßig sehen sie ihre eigenen Aufgaben, die anderer Personen nur mit dem Recht „Aufgaben: alle Personen sehen“ (tasks.view_all); Administratoren immer.',
    'illustrationen' => 'Alle angemeldeten Benutzer. Die Personenlisten zeigen nur Personen, die für die Rolle des Benutzers sichtbar sind.',
    'produkte' => 'Alle angemeldeten Benutzer.',
    'jobload' => 'Alle angemeldeten Benutzer; gebucht werden die Stunden der eigenen Person.',
    'jobload.overview' => 'Alle, die sich anmelden können (jeder Zugang gehört zu einer Person). Die eigenen Stunden immer, andere Personen nur mit dem Recht „Zeiterfassung: andere einzelne Personen einsehen“ (jobload.overview.view_others), die kundenweite Auswertung nur mit „Zeiterfassung: kundenweite Auswertung nach Jobgruppen sehen“ (jobload.overview.customer_summary); Administratoren immer.',
    'kalender' => 'Super-Admin sowie Personen, bei denen der Kalender aktiviert ist (in den Personendetails). Einträge anderer Personen nur mit dem Recht „Kalender: Einträge anderer Personen anlegen, bearbeiten und löschen“ (calendar.entries.manage_others); Administratoren immer.',
    'planung.index' => 'Benutzer mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view) sowie Personen, die Mitglied in mindestens einer Funktionsgruppe sind; Administratoren immer.',
    'planung.projektplanung' => 'Benutzer mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view) sowie Personen, die Mitglied in mindestens einer Funktionsgruppe sind; Administratoren immer. Die Organisationsauswahl hat nur, wer alle Organisationen sehen darf.',
    'planung.stunden' => 'Mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer.',
    'planung.arbeitszeit' => 'Mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer.',
    'planung.erinnerungen' => 'Mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer. Es erscheinen nur Erinnerungen zu Projekten, die die Person öffnen darf.',
    'planung.grundlast' => 'Ansehen mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Anlegen, Ändern, Löschen und Kopieren zusätzlich mit dem Recht „Planung: Grundlast anlegen, ändern und löschen“ (planning.base_load.edit); Administratoren immer.',
    'planung.grundlast-person' => 'Ansehen mit dem Recht „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Anlegen, Ändern, Löschen und Kopieren zusätzlich mit dem Recht „Planung: Grundlast anlegen, ändern und löschen“ (planning.base_load.edit); Administratoren immer.',
    'admin.kunden' => 'Administratoren, und nur wenn mehrere Organisationen aktiviert sind.',

    // Dialoge der Projektübersicht
    'D-E4DM' => 'Alle angemeldeten Benutzer (der Projektfilter gehört dem jeweiligen Benutzer).',
    'D-A2LP' => 'Alle angemeldeten Benutzer (der Anzeigefilter gehört dem jeweiligen Benutzer).',
    'D-NRG3' => 'Mit dem Recht „Projekt: neu anlegen“ (project.create); Administratoren immer.',

    // Projektdetails (D-MFQU)
    'D-MFQU' => 'Mit dem Recht „Projekt: Details aufrufen“ (project.view); Administratoren immer.',

    // Dialog "Zeiterfassung" am Projekt (D-78FH)
    'D-78FH' => 'Alle, die das Projekt öffnen können. Gebucht wird immer für die eigene Person (jeder Zugang gehört zu einer Person).',
    'D-78FH#buchungen' => 'Alle, die das Projekt öffnen können (jeder Zugang gehört zu einer Person). Jede Person sieht und löscht nur ihre eigenen Buchungen, auch Administratoren.',
    'D-78FH#jobs' => 'Mit dem Recht „Jobs am Projekt verwalten“ (project.jobload.manage); Administratoren immer.',
    'D-78FH#aufteilung' => 'Mit dem Recht „Jobs am Projekt verwalten“ (project.jobload.manage); Administratoren immer. Nur bei einem Hauptprojekt mit Unterprojekten.',

    // Overlay "Projektbeteiligte Personen" am Projekt (D-XNXQ)
    'D-XNXQ' => 'Alle, die das Projekt öffnen können. Speichern nur mit den Rechten „Projekt-Stammdaten bearbeiten“ (project.edit) und, wenn sich Personen ändern, „Projektbeteiligte hinzufügen/entfernen“ (project.people.manage); Administratoren immer.',

    // Projektdetails (D-MFQU), Reiter Planung und Zeiten
    'D-MFQU#vorgaenge' => 'Alle, die das Projekt sehen. Erfassen, hervorheben, ändern und löschen mit dem Recht „Projekt: Stammdaten bearbeiten“ (project.edit); Ändern und Löschen nur bei eigenen, von Hand erfassten Vorgängen, Administratoren bei allen von Hand erfassten.',
    'D-MFQU#planung.planstunden' => 'Alle, die das Projekt sehen. Ändern nur mit dem Planungsrecht (planning.view); Administratoren immer.',
    'D-MFQU#planung.auslastung' => 'Mit den Rechten „Projekte ansehen“ und „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer.',
    'D-MFQU#planung.terminuebersicht' => 'Alle, die das Projekt sehen. „Termine im Verbund verschieben“ nur am Hauptprojekt und mit dem Recht für Termine der Workflow-Schritte (workflow_step.due_date); Administratoren immer.',
    'D-MFQU#planung.ablaufplan' => 'Alle, die das Projekt sehen. Ändern nur mit dem Recht für Termine der Workflow-Schritte (workflow_step.due_date); Administratoren immer.',
    'D-MFQU#zeiten.uebersicht' => 'Alle, die das Projekt sehen (Summen ohne Personenbezug).',
    'D-MFQU#zeiten.personen' => 'Mit den Rechten „Projekte ansehen“ und „Planung: erweiterte Planung und personenbezogene Auswertungen“ (planning.view); Administratoren immer.',
    'D-MFQU#zeiten.gesamt' => 'Alle, die das Projekt sehen. Die Aufschlüsselung nach Personen oder Jobs nur mit dem Planungsrecht (planning.view).',
];
