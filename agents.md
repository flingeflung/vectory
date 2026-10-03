# AGENTS.md

## Rolle

Du arbeitest als Implementierer und technischer Analyst.
Architektur, Review und Aufgabenplanung übernimmt ein Kollege (separate Claude-Session).
Setze ausschließlich den beschriebenen Auftrag um.

## Projekte

- Vectory ist das neu zu entwickelnde System.
- Projektverzeichnis Vectory: `D:\htdocs\vectory`
- Vietto ist ausschließlich ein lesendes Referenzsystem.
- Projektverzeichnis Vietto: `D:\htdocs\vietto`

## Vietto als Referenz

- Vietto darf niemals verändert werden.
- Keine Änderungen an Dateien, Konfiguration oder Datenbank von Vietto.
- Keine Schreibzugriffe, Migrationen, Tests mit Datenänderungen oder sonstigen Eingriffe.
- Vietto dient ausschließlich der Analyse bestehender Funktionalität.
- Analysiere nur Navigationspunkte mit `navigation.blnActive = 1` sowie die von dort erreichbaren Folgepfade.
- Inaktive oder als „alt“ gekennzeichnete Bereiche nicht berücksichtigen, sofern sie nicht ausdrücklich beauftragt werden.
- Zentraler Einstiegspunkt für die Projektanalyse ist `Projekte` mit dem Ziel `projekte.php`.

## Vectory

- Vectory muss als eigenständiges System entstehen.
- Keine gemeinsame Konfiguration, Datenbank, Laufzeit- oder Speicherstruktur mit Vietto.
- Funktionen aus Vietto nicht unkritisch kopieren, sondern fachlich analysieren und für Vectory neu konzipieren.
- Veraltete Architektur, unsichere Muster und abgekündigte Bibliotheken nicht übernehmen.
- Vectory soll modern, sicher, mehrsprachig, mandantenfähig und langfristig wartbar sein.
- Hohe Konfigurierbarkeit ist erwünscht, unnötige Komplexität jedoch zu vermeiden.

## Arbeitsweise

- Vor Implementierungen bestehende Architektur und betroffene Abläufe analysieren.
- Änderungen möglichst klein und zielgerichtet halten.
- Keine zusätzlichen Funktionen, Refactorings oder Optimierungen ohne ausdrücklichen Auftrag.
- Keine spekulativen Änderungen.
- Bestehende Konventionen von Vectory beibehalten.
- Fachliche Annahmen klar als solche kennzeichnen und nicht eigenständig festschreiben.

## Git

- Arbeit erfolgt direkt auf Branch `main` (kein Team, keine Feature-Branches nötig).
- Vor Beginn einer neuen Aufgabe: `git pull origin main`, damit nichts überschrieben wird.
- Nach jeder abgeschlossenen Änderung automatisch: `git add`, `git commit` mit aussagekräftiger Nachricht. Ein `git push` erfolgt ausschließlich nach ausdrücklichem OK von Ralf (Stand 03.10.2026): Wenn eine Änderung committet ist, dann weise im Abschlussbericht auf die ungepushten Commits hin und warte auf die Freigabe.
- Wenn Ralf das OK zum Pushen gibt, dann führe `git pull origin main` und danach `git push origin main` aus.
- Branch, Merge, Rebase oder Tag ausschließlich nach ausdrücklicher Anweisung.

## Composer / Frontend

- Composer nur ausführen, wenn Composer-Dateien geändert wurden oder neue Abhängigkeiten erforderlich sind.
- npm/Vite nur ausführen, wenn Frontend-Dateien geändert wurden oder dies ausdrücklich verlangt wird.
- Keine neuen Abhängigkeiten ohne fachliche oder technische Notwendigkeit.

## Datenbank

- Datenbankänderungen nur im Vectory-Projekt.
- Migrationen nur erstellen oder ändern, wenn dies Bestandteil des Auftrags ist.
- Keine Daten oder Strukturen in der Vietto-Datenbank verändern.
- Datenmodelle nicht allein aus der bestehenden Vietto-Struktur ableiten, sondern fachlich prüfen.

## Sicherheit und Qualität

- Keine Zugangsdaten, Tokens oder Passwörter im Quellcode hinterlegen.
- Neue Konfigurationswerte über `.env` und `.env.example`.
- Sicherheitsmechanismen nicht abschwächen oder umgehen.
- Keine Tests verändern, um Fehler zu verdecken.
- Nach Änderungen relevante Syntaxprüfungen und Tests ausführen.

## Prüfliste vor Abschluss einer Aufgabe

Aus dem Review vom 03.10.2026: An diesen Stellen sind bisher Fehler entstanden. Gehe die Liste vor jedem Commit durch und nenne im Abschlussbericht, welche Punkte zutrafen.

1. **Rechte und Personen ändern:**
   - Wenn eine Aktion Zugriffsstufen, Rechte oder Personen verändert, dann prüfe auch den aktuellen Zustand des Ziels, nicht nur den neuen Wert (Beispiel: Ein Zentral-Admin darf keinen anderen Zentral-Admin zurückstufen).
   - Neue Rechte, Gates und Zugriffsregeln werden mit Super-Admin, Zentral-Admin, Organisations-Admin und User mit und ohne Recht getestet.
   - Wenn sich ein Recht ändert, dann halte `docs/zugriffsstufen-und-rechte.md` und die Kommentare im Code aktuell.
2. **Migrationen mit Daten:**
   - Wenn eine Migration vorhandene Daten umhängt, zusammenführt oder löscht, dann hänge Zeilen per UPDATE um und kopiere sie nicht.
   - Wenn dabei Kollisionen möglich sind, dann führe die Werte zusammen und prüfe abhängige Tabellen mit Löschkaskade (z. B. `task_visibilities`).
   - Teste solche Migrationen mit Dubletten und abhängigen Daten.
   - Wenn sich eine Migration nicht umkehren lässt, dann schreibe das als Kommentar in die Migration.
   - Wenn eine Migration Rechte erweitert, dann nenne das ausdrücklich im Abschlussbericht.
3. **Mandantengrenzen:**
   - Wenn eine Abfrage Daten mehrerer Organisationen liest oder ändert, dann umgehe globale Scopes nur lokal und begründet.
   - Wenn ein Datensatz keine eigene `tenant_id` hat, dann prüfe den Zugriff über den zugehörigen Datensatz und teste fremde Organisationen mit einem eigenen Test.
   - Organisationen lassen sich deaktivieren (`tenants.is_active`). Eloquent-Modelle mit `BelongsToTenant` blenden deren Daten über die zweite Schutzregel `tenant_active` automatisch aus. Wenn du eine rohe `DB::table(…)`-Abfrage auf Personen, Projekte oder Auswertungen baust, dann schließe `Tenant::inactiveIds()` aus (`whereNotIn`) und liste wählbare Organisationen mit `Tenant::query()->active()`.
   - Wenn ein Modell eine Person zuordnet (Projektbeteiligte, Aufgaben …), dann binde `HidesInactiveOrganizationPersons` ein, sonst bricht die Ansicht ab, sobald die Person ausgeblendet ist.
4. **Live-Aktualisierung zwischen Tabs und Overlays:**
   - Wenn dieselben Daten in mehreren Tabs oder Bausteinen stehen, dann prüfe jeden Baustein, der sie anzeigt, und nicht nur den, den du gerade änderst.
   - Wenn Projektpersonen oder Planstunden geändert werden, dann aktualisieren sich alle Anzeigen darauf über die Events `project-people-changed` und `project-planned-hours-changed`.
   - Wenn nicht gespeicherte Eingaben im Tab stehen, dann dürfen sie beim Nachladen nicht verloren gehen.
5. **Texte:**
   - Wenn du neue Texte mit `__()` einbaust, dann führe danach `php artisan lang:sync en` aus. Leere englische Werte bleiben als Platzhalter für den Übersetzer stehen und werden nicht selbst übersetzt.
   - Texte an den Nutzer stehen in der Sie-Form, im Stil „Wenn X, dann Y“.
6. **Alpine und Blade:**
   - Wenn du ein `x-data="…"`-Attribut änderst, dann darf darin kein gerades Anführungszeichen stehen, auch nicht in einem Kommentar. Prüfe jede geänderte Datei sofort danach.
7. **Doppelte Rechenwege:**
   - Wenn dieselbe Regel (Arbeitstage, Stunden, Rechte) an zwei Stellen berechnet wird, dann nutze die gemeinsame Stelle (`Workdays`, `PersonAnnualHoursCalculator`).
8. **Kommentare und Doku:**
   - Wenn sich ein Verhalten ändert, dann korrigiere Kommentare und Doku, die das alte Verhalten beschreiben.
9. **Dialoge (Overlays):**
   - Wenn du einen neuen Dialog (`<x-modal name="…">`) anlegst, dann führe `php artisan dialogs:sync` aus, damit er eine feste Dialog-ID bekommt (`config/dialog-ids.php`).
   - Wenn du einen Dialog umbenennst, dann trage den neuen Namen mit der ALTEN ID in `config/dialog-ids.php` ein und lösche den alten Eintrag. Sonst verliert die zugehörige Hilfeseite ihre Zuordnung. Ein Test warnt dich davor.
10. **Tests:**
   - Führe vor dem Commit die gesamte Testsuite aus (`php artisan test`).
   - Wenn du einen Controller mit Checkboxen testest, dann sende alle Checkbox-Felder mit. Fehlen sie, dann werden Werte stillschweigend auf „aus“ gesetzt.
   - Teste nie mit unvollständigen Requests an echten Datensätzen.

## Abschlussbericht

Sofern nicht anders gefordert, berichte ausschließlich:

1. Geänderte oder analysierte Dateien
2. Ausgeführte Befehle
3. Testergebnisse oder Analyseergebnis
4. Offene Punkte und fachliche Annahmen
5. Angewendete Punkte der Prüfliste (welche trafen zu, was wurde getan)
