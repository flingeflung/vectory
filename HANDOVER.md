# Handover an Codex (2026-09-29)

Grund: Ralfs Claude-Nutzungslimit läuft bis Freitag knapp, Codex übernimmt bis
zum Reset. Diese Datei ist ein Schnappschuss des aktuellen Stands - bitte
zuerst lesen, dann archivieren, sobald sie nicht mehr aktuell ist.

**Zuerst lesen, in dieser Reihenfolge:**
1. `agents.md` (Projekt-Root) - regelt die Arbeitsweise/Git-Workflow für dich als Implementierer. Wichtig: dort steht "nach jeder Änderung automatisch commit+push" - das ist dein Standardverhalten, nicht das von Claude (siehe unten).
2. `CLAUDE.md` (Projekt-Root) - alle UI-Konventionen (Speichern-Button-Regeln, Overlay-Muster, Sicherheitsabfragen, Sortierung, Formulierungsstil usw.). Bitte konsequent einhalten, Ralf achtet stark auf Konsistenz.

## Wer ist Ralf, wie arbeitet er

- Solo-Gründer, baut Vectory als sein eigenes Produkt (Nachfolger von Vietto, seinem alten internen Tool). Kein Team.
- Bevorzugt kurze, prägnante Antworten ("keine Romane"), keine Emojis.
- Gibt fachlich vor, was gebaut wird; Umsetzungsdetails liegen bei dir, aber Vorschläge sind willkommen.
- Erwartet, dass offene Design-Entscheidungen/Widersprüche zu früheren Entscheidungen VOR dem Bauen angesprochen werden, nicht stillschweigend gelöst.
- Testet oft selbst live im Browser und meldet Bugs sehr präzise - nimm seine Beobachtungen ernst, auch wenn sie erstmal unplausibel klingen (in dieser Session gab es mehrfach echte, nicht-offensichtliche Bugs, die sich bei genauerem Hinsehen bestätigten).
- Bei Bug-Meldungen an ihn: nur den beobachtbaren Effekt und "kein Handlungsbedarf" o.ä. beschreiben, KEINE Klassennamen/Exception-Texte/Methodennamen - das hat er explizit zurückgewiesen.
- Deutsche UI-Texte: er ist studierter technischer Redakteur, bevorzugt "Wenn X, dann Y" statt gestapelter Verb-Erst-Konstruktionen in Hinweistexten.

## Aktueller fachlicher Kontext: Personelle Ressourcenplanung

Übergeordnetes Ziel (Ralf, 2026-09-29): Jahresstunden (= "zur Verfügung
stehende" Kapazität einer Person) sollen später gegen die für Projekte
eingeplanten Bedarfsstunden gegengerechnet werden. Die Differenz soll einem
Redaktionsleiter/Projektleiter zeigen, ob eine Person für das nächste Projekt
noch Kapazität hat. Alles bisher Gebaute (siehe unten) ist die Kapazitätsseite
davon - die Bedarfsseite und der eigentliche Abgleich sind NOCH NICHT gebaut.

### Bereits fertig und getestet (heutige + gestrige Session)

**1. Wochenstunden-Historie** (`person_weekly_hours`-Tabelle, Model
`PersonWeeklyHours`, Relation `Person::weeklyHours()`) - löst das frühere,
statische Feld `people.weekly_hours` ab (Spalte ist entfernt). Grund: eine
Person kann ihre WoSt im Jahresverlauf mehrfach ändern, ein einzelner Wert
würde eine Jahresplanung verfälschen.
- Rein anhängbare Historie: `valid_from`/`valid_to` (beide nullable). Der
  allererste Datensatz einer Person darf ohne Start sein ("schon immer so").
  Jeder weitere Datensatz braucht zwingend einen Start; beim Anlegen wird
  automatisch das `valid_to` des bisher offenen (letzten) Datensatzes auf
  `neuer_start - 1 Tag` gesetzt (siehe `PersonController::storeWeeklyHours()`).
  KEINE nachträgliche Bearbeitung/Löschung bestehender Zeilen (bewusste
  Ralf-Entscheidung).
- UI: Feld "Wochenstunden" (nur lesend, aktueller Wert) + "verwalten"-Button
  in den Personendetails, öffnet ein eigenes globales Overlay
  (`layouts/app.blade.php`, Modal `person-weekly-hours`,
  `admin/personen/partials/weekly-hours-body.blade.php`). Nur bei Personen MIT
  Login sichtbar (`$person->user`) - nur die buchen Stunden.
- Nur für Personen mit Login relevant. Beim Anlegen eines Logins
  (`PersonController::createLogin()`) wird automatisch ein erster Datensatz
  mit dem Mandanten-Standardwert (`tenants.default_weekly_hours`) angelegt.

**2. Urlaubstage-Historie** - exakt dieselbe Systematik wie oben, eigene
Tabelle `person_vacation_days`, Model `PersonVacationDays`, Relation
`Person::vacationDays()`. Eigenes Overlay (Modal `person-vacation-days`,
`vacation-days-body.blade.php`). Mandanten-Standardwert
`tenants.default_vacation_days` (Default 30).

**3. Hauptnavigationspunkt "Planung"** (`PlanningController`,
`App\Support\PlanningNav`, `resources/views/components/planning-layout.blade.php`,
gleiches Reiter-oben/Inhalt-darunter-Design wie der Admin-Bereich). Aktuell
ein Reiter "Stunden". Rechtegesteuert über neues, granulares Recht
`planning.view` (in der `permissions`-Tabelle, gleiches Muster wie
`project.hours.person_breakdown` - Ralf weist es selbst zu, startet bei
niemandem automatisch).

**4. Reiter "Stunden"** (`PlanningController::stunden()`,
`resources/views/planning/stunden.blade.php`): Jahres-Dropdown (nur Jahre, für
die irgendeine sichtbare Person WoSt-Daten hat, plus immer das laufende Jahr),
Tabelle Vorname/Nachname/WoStd/Arbeitstage/Urlaub(Std)/Jahresstd. je Person +
Summenzeile.
- **Formel** (von Ralf per Excel-Mockup bestätigt): Arbeitstage = reine
  Wochentage (Mo-Fr) des Jahres, bewusst OHNE Feiertage/Krankheitstage
  (Hinweis-Tooltip in der UI). Jahresstd. = WoSt/5 × Arbeitstage, abzüglich
  Urlaubstage × WoSt/5. Ändert sich WoSt ODER Urlaubstage unterjährig, wird
  abschnittsweise gerechnet (siehe `overlappingSum()`/`countWeekdays()` in
  `PlanningController`) statt mit einem einzigen Jahreswert hochzurechnen.
  Die angezeigten "WoStd"/"Urlaub (Std)"-Spalten sind dabei aus der korrekt
  berechneten Jahresstd.-Summe zurückgerechnete Durchschnittswerte.
  **WICHTIG - offener Punkt:** Diese Formel liegt aktuell NUR im
  `PlanningController` (nicht wiederverwendbar). Ralf hat explizit gesagt,
  dass er sie für die nächsten Schritte (Bedarfsabgleich) braucht -
  Auslagerung in eine eigenständige, von der Seite unabhängige
  Berechnungs-Klasse ist vorgemerkt, aber noch nicht gemacht. "Ist mir egal,
  wann du das machst" - also frei, wann es passiert, aber PFLICHT bevor eine
  zweite Stelle dieselbe Rechnung braucht.
- **Wer wird angezeigt:** nur Personen mit Login, für die `people.resource_planning`
  = true ist (neues Boolean-Feld, Default true) - eine direkte Checkbox
  "Ressourcenplanung" in den Personendetails (Tooltip "Diese Person in die
  Ressourcenplanung mit einbeziehen"), gleiche Position wie
  Wochenstunden/Urlaubstage. Zusätzlich ein Filter-Dropdown
  "Ressourcenplanung" (Alle/enthalten/nicht enthalten) in der
  Personenübersicht (`admin/personen`), weil Ralf das Feld teils direkt in
  der DB pflegt und im GUI durchsehen will.
- **Direktes Bearbeiten:** jede Zeile hat ein kleines Bearbeiten-Symbol
  (`<x-edit-icon-button>`), öffnet das GLOBALE Personen-Overlay
  (`open-person`-Event, existiert schon lange fürs restliche Tool). Die
  Tabelle aktualisiert sich danach automatisch per fetch() (Listener auf
  `close-modal`/`person-overlay` in `stunden.blade.php`).

**5. Terminologie:** "Subunternehmer" → "Dienstleister" umbenannt
(`SystemSetting::companyLabel()`) - passt besser auch für eine einzelne
freiberuflich arbeitende Person, nicht nur eine Firma. Überall konsistent
nachgezogen (Tooltip-Texte, Kommentare, `lang/en.json`).

### Nicht bauen ohne ausdrücklichen Auftrag (Backlog, von Ralf so gewünscht)

- **Projekt-Zugriffskonzept überarbeiten**: wer darf ein Projekt sehen/bearbeiten
  (aktuell z.B. kann jeder TR ein Projekt bearbeiten, dem er nicht zugewiesen
  ist - für TR laut Ralf ok, aber offene Frage für andere Rollen). Nur
  Diskussionspunkt, keine Umsetzung.
- **Force-Logout**: eine laufende Session eines Users sofort zwangsbeenden
  können (z.B. bei ausscheidenden Mitarbeitenden). Nur Diskussionspunkt.
- **Termine-Dialog aktualisiert den Rest des Overlays nicht live** (bekannter,
  akzeptierter Randfall, kein akuter Fix nötig).

## Wichtige technische Fallstricke (in dieser Session gefunden)

1. **Mandanten-Scope bei Relationen, die zum HEIMAT-Mandanten einer Person
   gehören, nicht zum gerade aktiven**: `BelongsToTenant` filtert automatisch
   nach dem AKTIVEN Mandanten. Für Relationen wie `company()`, `department()`,
   `permissionTemplate()`, `weeklyHours()`, `vacationDays()` auf `Person`
   IMMER `->withoutGlobalScope('tenant')` verwenden - sonst verschwinden Daten
   silently, sobald der aktive Mandant einer Person vom gerade eingeloggten
   User abweicht (echter Produktionsbug, der Ralf schon einmal getroffen hat).
2. **`Validator::validated()`** lässt Felder, die komplett im Request fehlen,
   automatisch weg (selbst mit nur `nullable`) - kein Grund, deshalb tote
   Validierungsregeln stehen zu lassen, aber auch kein SQL-Crash-Risiko.
3. **`response()->view(..., ['errors' => $validator->errors()])`** übergibt
   eine rohe `MessageBag` - Blades `@error()`-Direktive braucht zwingend eine
   `ViewErrorBag` und crasht sonst mit einem Fatal Error. Fix bereits
   vorhanden: `Controller::viewErrors(MessageBag $errors): ViewErrorBag`
   (Basis-Controller-Klasse) - IMMER diesen Helper verwenden, nicht die rohe
   MessageBag durchreichen. Gleiches Muster kommt laut Grep evtl. noch in
   `ProjectController.php`/`MultichangeController.php` vor - dort nicht
   geprüft.

## Git-Stand

- Branch `main`, aktueller HEAD: `d3499cb`.
- Alles bis `d3499cb` ist auf `origin/main` gepusht - kein lokaler Rückstand.
  Bitte laut `agents.md` ab jetzt wieder normal nach jeder Änderung
  committen UND pushen.
- Migrationen sind bereits lokal ausgeführt (`php artisan migrate`), Backfill
  ist erfolgt - keine offenen Migrationen.
- Es gibt zusätzlich eine Backlog-Übersichtsseite, aber die ist an Claudes
  Oberfläche gebunden (kein normaler Link, für andere Tools nicht öffenbar) -
  nur für Ralf relevant, nicht für dich. Alles, was du als Codex brauchst,
  steht hier oder in den lokalen Dateien.

## Wo die Datei liegt

`D:\htdocs\vectory\HANDOVER.md` (Projekt-Root, neben `CLAUDE.md`/`agents.md`).
