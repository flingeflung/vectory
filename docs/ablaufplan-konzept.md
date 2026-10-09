# Ablaufplan: Phasen und Meilensteine (Konzeptentwurf)

Stand: 2026-10-09, Entwurf zur Abstimmung. Es ist noch nichts gebaut.

## 1. Ausgangslage

Ein Workflow-Schritt (WFS) trägt heute mehrere Dinge zugleich:

- **Dauer** (`duration_days`, Arbeitstage) und Sperre (`duration_locked`),
- **Termin** (`due_date` am Projektschritt, Name in `milestone_title`, Schalter `has_due_date` am Workflow-Schritt),
- **Markierung** als Projektstart/-ende (`is_start`, `is_end`) und als Markteinführung (`is_market_launch`),
- **Schalter „aktiv“** (`is_active`): inaktive Schritte erscheinen als „nur Termin, kein WFS“ und werden in der Steuerung übersprungen,
- **Projektstatus** (`lifecycle_status` 1–4: Geplant, In Bearbeitung, Beendet, Verworfen),
- **Erledigt-Zeitpunkte** (`completed_at`, `milestone_done_at`).

Folgen:

- Das Datum neben einem Schritt ist Zeitraum und Termin zugleich. Es ist nirgends erkennbar, ob es den Anfang oder das Ende meint (technisch ist es das Ende).
- „Termine berechnen“ rechnet nur zwischen den Terminen. Der Ablaufplan summiert dagegen alle Schritte. Beide kommen zu verschiedenen Ergebnissen (Beispiel Projekt 260003: 10 AT gegen 49 AT).
- Termin, Meilenstein und Arbeitsschritt sind nicht getrennt (Erbe aus Vietto).

## 2. Begriffe

- **WFS (Workflow-Schritt):** die Manifestation einer Projektphase in Vy. Er dient der Steuerung: Ablauf, beteiligte Personen, Benachrichtigungen, Freigaben.
- **Phase:** die Zeitspanne eines WFS im Plan, mit Start, Ende und Dauer. Sie hat den Namen des WFS (z. B. „Anleitung erstellen“, „Lektorat“, „Externe Übersetzung“).
- **Phasenende:** der letzte Tag einer Phase. Es kann einen eigenen Namen tragen, der das Zielbild der Phase beschreibt (z. B. „Korrekturexemplar erstellt“, „Lektorat durchgeführt“, „Übersetzung fertig“). Den Namen legt, wer den Workflow gestaltet.
- **Meilenstein (Vy-Meilenstein):** ein frei gesetzter Zeitpunkt ohne eigene Phase (z. B. „Markteinführung“, „Prototypenbau“).
- **Status-Schritte:** Die Schritte mit den Status „Geplant“ (im Beispiel „In Planung“), „Beendet“ („Projektende“) und „Verworfen“ („Projekt verworfen“) setzen den Projektstatus. Sie sind keine Phasen und haben keine Dauer im Plan.

## 3. Zielmodell

### Phase

- Jeder Arbeitsschritt (heute: Schritte „In Bearbeitung“) bildet eine Phase mit **Start**, **Ende** und **Dauer** in Arbeitstagen.
- Die Dauer zählt inklusive des Endtags. Eine 1-AT-Phase beginnt und endet am selben Tag.
- Eine Phase beginnt am nächsten Arbeitstag nach dem Ende der vorigen. Wochenenden und Feiertage der Organisation zählen nicht mit.
- Das Ende einer Phase bedeutet immer: „Die Phase wurde erfolgreich durchgeführt.“
- Sperre (Schloss) und Voreinstellung der Dauer bleiben wie heute.
- Der Schalter „aktiv“ (heute „nur Termin, kein WFS“) **entfällt**. Jeder WFS ist eine Phase und wird in Vy gesteuert. Was in einer Phase geschieht, ergibt sich aus ihrem Namen.

### Phasenende als Termin

- Jedes Phasenende hat ein berechnetes Datum.
- Ein Phasenende **mit Namen** gilt als Termin: Es erscheint in Terminübersicht, Projektfamilie und Kalender, und Kritische Projekte meldet es, wenn es überschritten ist. Der eigene Schalter „Termin“ (`has_due_date`) entfällt.

### Workflow-Zeitraum

- **Start** des Workflows = Beginn der ersten Phase, **Ende** = Ende der letzten Phase. Beides ist berechnet und deckt sich mit Projektstart und -ende.
- Die heutigen Kennzeichen „Projektstart“ und „Projektende“ an einzelnen Schritten entfallen als Bedienung. Sie ergeben sich aus der Kette.

### Meilenstein

- Ein Meilenstein ist ein **Punkt** ohne Dauer und ohne Arbeit, mit Namen und Datum.
- Er kann **beliebig oft** angelegt werden, im Workflow (Vorlage) und im Projekt.
- Er bezieht sich auf genau eine der folgenden Arten:
  1. Abstand vom **Workflow-Start**,
  2. Abstand vom **Workflow-Ende**,
  3. Abstand vom **Start** einer bestimmten Phase,
  4. Abstand vom **Ende** einer bestimmten Phase,
  5. **festes Datum** (wandert nicht mit).
- Abstände sind Arbeitstage, vor oder nach dem Bezugspunkt.
- **Ein Meilenstein hängt von Phasen ab, nie umgekehrt.** Verschiebt sich eine Phase, wandern abhängige Meilensteine mit. Ein Meilenstein verschiebt nie eine Phase.
- **Ein Meilenstein bezieht sich nie auf einen anderen Meilenstein.** Dadurch sind Zirkelbezüge ausgeschlossen.
- Ein Meilenstein ist nur eine Marke und begrenzt keine Phase; der Plan ändert sich dadurch nie. Konflikte werden als Befund in **Kritische Projekte** gemeldet, nicht im Ablaufplan erzwungen.
- Die Kennzeichnung „Markteinführung“ hängt künftig am Meilenstein.
- *Vorschlag, noch offen:* Ein Meilenstein darf auch **vor dem Projektstart** oder **nach dem Projektende** liegen (z. B. „Prototypenbau“ vor Beginn, „Markteinführung“ Monate nach dem Druck). Start und Ende des Workflows bleiben dabei die Grenzen der Phasen und werden von Meilensteinen nicht verschoben. Siehe offene Punkte.

### Fixpunkt (Zielscheibe)

- Jedes Datum im Ablaufplan trägt eine kleine Zielscheibe. Ein Klick macht dieses Datum zum Fixpunkt.
- Mögliche Fixpunkte: Workflow-Start, Workflow-Ende, Start oder Ende einer Phase, ein Meilenstein.
- Wirkung: einmalige Rechenaktion. Die Kette wird so verschoben, dass der gewählte Punkt dieses Datum trifft. Es gibt keinen dauerhaft „festen“ Zustand.
- Das Ergebnis erscheint als Vorschau im Diagramm. Übernommen wird über „Speichern“.
- Mit einem Fixpunkt am Anfang (z. B. festgelegter Entwicklungsstand) rechnet die Kette vorwärts, mit einem Fixpunkt am Ende (z. B. Messetermin) rückwärts, mit einem Fixpunkt in der Mitte nach beiden Seiten.
- Sind Start und Ende beide vorgegeben und passt die Summe der Dauern nicht, gilt wie heute der Hinweis „Es fehlen n AT“. Ob dann Dauern gekürzt oder ein Datum verschoben wird, entscheidet der Mensch.
- Der Knopf „Termine berechnen“ und sein Dialog entfallen. Die Rechnung gibt es nur noch an einer Stelle.

## 4. Trennung von Planung und Steuerung

- **Ablaufplan (Planung):** Soll. Phasen, Dauern, Sperren, Phasenenden, Meilensteine, Fixpunkt.
- **Workflow-Ansicht (Steuerung):** Ist. Aktueller WFS, Erledigt-Zeitpunkte, Verantwortliche, Aktionen.
- Beide zeigen dieselben WFS/Phasen und Meilensteine und müssen synchron bleiben. Meilensteine sind in beiden pflegbar.
- Soll und Ist werden nie in einem Feld vermischt. Ein Verzug ist der Abstand zwischen Soll-Phasenende und Ist-Erledigt.

## 5. Oberfläche

- **Ablaufplan unter dem Diagramm:** statt der losen Felderreihe eine **Tabelle** mit Spalten, etwa: Farbe, Phase, Sperre, Start, Ende, Phasenende-Name, Dauer (AT), Kalenderwoche, Zielscheibe. Meilensteine stehen als eigene Zeilen an ihrer zeitlichen Stelle.
- **Diagramm:** Balken (Phasen) und Rauten (Meilensteine) lassen sich verschieben. Beim Verschieben eines Meilensteins gelten die Bezugsregeln, ein fest datierter wird neu fixiert.
- **Workflow-Ansicht und Workflow-Verwaltung:** Meilensteine als eigene Art neben den WFS, mit Bezug (Art und Abstand). Die genaue Darstellung wird gesondert geklärt.

## 6. Betroffene Stellen

- Datenmodell: neue Tabelle für Meilensteine (Workflow-Vorlage und Projekt), Bezug und Abstand; die heutigen Felder `due_date`, `has_due_date`, `is_start`, `is_end`, `is_active` und `is_market_launch` am Schritt werden abgelöst, `milestone_title` wird zum Phasenende-Namen. Die alten Felder bleiben vorerst bestehen, damit die Sanitär-Daten lesbar bleiben; sie werden bei der Liefer-DB entfernt.
- Rechnung: `ProjectPlanningCalculator`, `ProjectStepTimeline`, `WorkflowScheduleCalculator` (entfällt in der heutigen Form).
- Weitere Verbraucher der Termine: Kritische Projekte (`CriticalProjectEvaluator`, zusätzlich neue Befunde für Meilenstein-Konflikte), Planung übertragen (Bereich „Termine der Schritte“), Projektkopie-Vorlagen (Bereich Meilensteine), Projektfamilie/Verbund (`ProjectFamilyTimeline`), Planungs-Kalender, Illustrationsaufträge, Aktivierung von Schritten, Import aus Vietto, Hilfetexte.
- Oberfläche: Ablaufplan, Terminübersicht, Workflow-Verwaltung, Projektdetails.

## 7. Bestand

- Die Vectory-Projekte der Organisationen **Heimat, Standard und Maschinen** werden in das neue Modell **migriert**. Die Vietto-Testdaten der Organisation Sanitär werden nicht migriert; der neue Code darf sie nicht beschädigen und zeigt sie nach der neuen Rechnung.
- Es sind erfundene Testprojekte, deshalb muss das Ergebnis grundsätzlich passen, aber nicht auf den Tag genau.
- Regeln:
  - Phasen und Phasenende-Daten werden aus Projektstart und Dauern neu berechnet. Die alten Termin-Daten an Arbeitsschritten entfallen.
  - Termin-Namen (`milestone_title`) werden zu Phasenende-Namen.
  - „Druck“ (bisher inaktiv) wird ein normaler WFS mit Phasenende „Fertig gedruckt“.
  - „Markteinführung“ (bisher inaktiver Schritt in Print 2026 bei Maschinen und Standard sowie in Online-Anleitung bei Standard) wird ein Meilenstein mit der Kennzeichnung Markteinführung. In den Vorlagen bezieht er sich auf das Workflow-Ende; in Projekten mit vorhandenem Datum wird dieses als festes Datum übernommen.
  - Heimat hat keine solchen Schritte.
- Neue Projekte entstehen ausschließlich im neuen Modell. Eine ausgelieferte Neuinstallation enthält keine Altdaten.

## 8. Umbau in Stufen

1. Konzept abstimmen und festhalten (dieses Dokument).
2. Datenmodell und Rechnung: Phasen, Meilensteine, Kette, Fixpunkt, mit Tests; Migration der Projekte und Vorlagen von Heimat, Standard und Maschinen.
3. Ablaufplan als Tabelle und Diagramm mit Meilensteinen.
4. Workflow-Verwaltung und Workflow-Ansicht für Meilensteine, Synchronität.
5. Zielscheibe, Wegfall von „Termine berechnen“.
6. Folgestellen (Kritische Projekte, Planung übertragen, Kopiervorlagen, Verbund, Hilfetexte).

## 9. Offene Punkte

1. Meilensteine vor Projektstart und nach Projektende (Vorschlag in Abschnitt 3) und, damit verbunden, die **Prüfrichtung** eines Meilensteins: ob er eine *Voraussetzung* ist (z. B. „Prototypenbau“: das Projekt darf nicht vorher beginnen) oder ein *Ziel* (z. B. „Markteinführung“: der Druck muss vorher fertig sein). Daraus ergeben sich die Befundregeln in Kritische Projekte (welche Konflikte, welche Dringlichkeitsstufe).
2. Darstellung der Meilensteine in der Workflow-Ansicht.
3. Verhalten der Verbundprojekte (Hauptprojekt und Unterprojekte) mit Fixpunkt und Meilensteinen.

---

## Anhang A: Technischer Bauplan Stufe 2 (Datenmodell, Rechnung, Migration)

Zielgruppe: Umsetzung. Stufe 2 ändert die Oberfläche nur dort, wo sonst Widersprüche entstünden (A.7). Alles andere bleibt für die Stufen 3–6.

### A.1 Grundidee: ein Schreiber, berechnete Felder als Zwischenspeicher

- Für Projekte im neuen Modell gibt es genau **einen** Schreiber aller Termine: den neuen Dienst `ProjectScheduler`.
- Eingaben der Rechnung sind nur: `projects.start_date`, die Dauern (Projektwert sonst Vorlage, mindestens 1), die Feiertage der Organisation und die Meilenstein-Definitionen.
- Ergebnisse werden in die **vorhandenen** Spalten geschrieben, damit alle heutigen Verbraucher (Kritische Projekte, Kalender, Projektfamilie, Terminübersicht, Listen, Filter) ohne Änderung weiterlaufen:
  - `project_workflow_steps.due_date` = berechnetes **Phasenende** (Arbeitsschritte, `lifecycle_status` 2),
  - Status-Schritt „Geplant“ (`lifecycle_status` 1): `due_date` = Projektstart; „Beendet“ (`lifecycle_status` 3): `due_date` = Projektende,
  - `projects.end_date` = Ende der letzten Phase,
  - `project_milestones.date` = berechnetes Meilenstein-Datum.
- Fehlende Projektschritt-Zeilen (Projekte haben nicht für jeden Schritt eine Zeile) legt der Dienst bei Bedarf an.
- Der Dienst schreibt über den Query-Builder (keine Model-Events), damit keine Schleifen mit Beobachtern entstehen.

### A.2 Unterscheidung alt/neu

- Neue Spalte `projects.schedule_model` (tinyint, **Standard 1** = alt).
- Projekte im neuen Modell haben `2`. Gesetzt durch: Neuanlage (`ProjectController` Zeile ~428), Projektkopie (`ProjectCopyController` Zeile ~251) und die Migration (A.6).
- Standard 1 schützt Sanitär und alle bestehenden Tests, die Termine von Hand setzen. Für `schedule_model = 1` ändert sich nichts.

### A.3 Neue Tabellen

`workflow_milestones` (Vorlage, je Workflow):
- `id`, `tenant_id` (cascade), `workflow_id` (cascade), `name`, `sort`,
- `anchor_type` (string): `workflow_start` | `workflow_end` | `step_start` | `step_end`,
- `anchor_workflow_step_id` (nullable, cascade; Pflicht bei `step_*`, nur Schritte mit `lifecycle_status` 2 desselben Workflows),
- `offset_days` (int, vorzeichenbehaftet, Arbeitstage; negativ = davor),
- `is_market_launch` (bool), `check_direction` (nullable string: `prerequisite` | `target`; erst in Stufe 6 ausgewertet), Zeitstempel.
- Keine festen Daten in der Vorlage.
- Wandert mit bei Neue Version, Kopieren und Konfiguration übernehmen des Workflows (wie `workflow_group_windows`) sowie bei `PresetCopy/WorkflowsArea`.

`project_milestones` (je Projekt):
- `id`, `tenant_id` (cascade), `project_id` (cascade), `workflow_milestone_id` (nullable, nullOnDelete), `name`, `sort`,
- `anchor_type`: wie oben, zusätzlich `fixed`,
- `anchor_workflow_step_id` (nullable), `offset_days` (int), `fixed_date` (date, nullable; Pflicht bei `fixed`),
- `is_market_launch`, `check_direction`,
- `date` (date, berechnet), `reached_at` (datetime, nullable, Ist), Zeitstempel.
- Modelle mit `BelongsToTenant` (Wächter `OrganizationGuardTest` beachten).

### A.4 Rechenregeln (`ProjectScheduler`)

- Arbeitstag: Mo–Fr und kein aktiver Feiertag der Organisation des Projekts. Die vorhandene Logik aus `WorkflowScheduleCalculator::addWorkingDays/subWorkingDays` in eine eigene Klasse `WorkdayCalendar` ziehen und dort wiederverwenden.
- Phasen = Schritte des **aktuellen** Workflows mit `lifecycle_status` 2, sortiert nach `sort`. Achtung: Projekte tragen nach einem Workflow-Wechsel auch Schritte alter Workflows; immer nach `workflow_id` filtern.
- Dauer = Projektwert, sonst Vorlage, mindestens 1 (wie `ProjectStepTimeline::MIN_STEP_DAYS`).
- Phase 1 beginnt am Projektstart (fällt der auf einen Nicht-Arbeitstag: nächster Arbeitstag). Ende = Beginn + (Dauer − 1) Arbeitstage. Die nächste Phase beginnt am nächsten Arbeitstag nach dem Ende der vorigen.
- Meilenstein-Datum: Bezugspunkt bestimmen (Workflow-Start/-Ende, Start/Ende der Phase), dann `offset_days` Arbeitstage vor bzw. zurück; bei `fixed` gilt `fixed_date`. Fehlt der Bezugsschritt (z. B. nach Workflow-Wechsel), bleibt `date` leer.
- Ohne Projektstart oder ohne Workflow: nichts berechnen, nichts löschen.
- Öffentliche Methoden:
  - `recalculate(Project)`: schreibt alles aus A.1.
  - `timeline(Project)`: liefert die Phasen mit Start/Ende/Dauer und die Meilensteine (für Diagramm und Tabelle in Stufe 3), ohne zu schreiben.
  - `startDateFor(Project, string $point, CarbonInterface $date)`: Fixpunkt. `$point` = `workflow_start` | `workflow_end` | `step_start:{id}` | `step_end:{id}` | `milestone:{id}` (nur relative Meilensteine). Liefert den Projektstart, bei dem der Punkt auf `$date` fällt (Abstand in Arbeitstagen vom Start zurückrechnen).

### A.5 Auslöser für `recalculate`

- `Project` gespeichert und `start_date` oder `workflow_id` geändert (Model-Hook, nur `schedule_model = 2`).
- `ProjectWorkflowStep` gespeichert und `duration_days` geändert.
- `Holiday` angelegt, geändert oder gelöscht: alle Projekte der Organisation mit `schedule_model = 2`, die nicht beendet oder verworfen sind.
- `ProjectMilestone` gespeichert: nur sein `date` neu.
- Workflow-Zuweisung (die `firstOrCreate`-Stellen in `ProjectController` ~929, `MultichangeController::applyWorkflow`, `ProjectCopyController` ~326 und `PlanningTransfer`): in einen gemeinsamen Helfer ziehen, der zusätzlich die Vorlagen-Meilensteine in `project_milestones` kopiert (vorhandene mit gleicher `workflow_milestone_id` nicht doppeln) und danach rechnet.
- `ProjectWorkflowStepObserver::syncProjectStartEndDate` überspringt Projekte mit `schedule_model = 2` (sonst Gegenrichtung).

### A.6 Migration (Artisan-Befehl, keine Schema-Migration)

`php artisan schedule:migrate-v2 {--tenant=*} {--dry-run}`, wiederholbar ohne Doppelung. Aufruf für Heimat (17), Standard (10), Maschinen (7). Ablauf je Organisation, ohne globale Scopes (Standard ist deaktiviert):

1. Workflow-Schritte mit `is_market_launch = 1`: je Workflow einen `workflow_milestone` anlegen (Name = `milestone_title`, sonst Titel; `anchor_type` = `workflow_end`; `offset_days` = 1; `is_market_launch` = 1; `check_direction` = `target`). Für jedes Projekt dieses Workflows einen `project_milestone` anlegen: mit vorhandenem `due_date` als `fixed` mit diesem Datum, sonst aus der Vorlage. Danach den Schritt löschen (die Projektschritte hängen per cascade daran).
2. Übrige Schritte mit `is_active = 0` (Druck): `is_active = 1`.
3. Für alle Schritte: `has_due_date` = (`milestone_title` nicht leer) oder `lifecycle_status` in (1, 3); `is_start` = erster Schritt mit `lifecycle_status` 1; `is_end` = erster Schritt mit `lifecycle_status` 3. Projekt-Übersteuerungen von `is_start`/`is_end` auf null.
4. Projekte: `schedule_model = 2`; fehlender `start_date` = `due_date` des Startschritts, sonst frühestes `due_date`, sonst Anlagedatum. Vorlagen-Meilensteine kopieren (A.5), dann `recalculate`.
5. Ausgabe: Anzahl Workflows, Schritte, Meilensteine, Projekte; Projekte ohne Start.

Für die Liefer-DB: Die Schema-Migrationen gehören dazu, der Befehl nicht (keine Altdaten).

### A.7 Notwendige kleine Oberflächen-Schutzmaßnahmen in Stufe 2

Für `schedule_model = 2`:
- Datumsfelder an WFS (Projektdetails, Ablaufplan) sind nur Anzeige, mit Tooltip „Wird aus Projektstart und Dauern berechnet.“; eine direkte Änderung würde beim nächsten Rechnen überschrieben.
- „Termine berechnen“ (Knopf und Symbole) ausblenden.
- `ProjectScheduleController::setPeriod`: `side=start` setzt `start_date`; `side=end` setzt `start_date = startDateFor(project, 'workflow_end', date)`. `saveDurations` und `setDurationLock` lösen `recalculate` aus.
- Alles Weitere (Tabelle, Meilensteine bearbeiten, Zielscheibe) folgt in den Stufen 3–5.

### A.8 Tests

- Kette: Start an einem Freitag mit 1-AT-Phase endet freitags, die nächste beginnt montags; Feiertag wird übersprungen; Start am Wochenende rückt auf Montag; Dauer 0 zählt als 1.
- `recalculate` schreibt `due_date` aller Phasen, des Start- und Ende-Schritts und `projects.end_date`; Schritte alter Workflows bleiben unberührt.
- Meilensteine: alle fünf Bezugsarten, negative Abstände, Datum vor Projektstart und nach Projektende, fehlender Bezugsschritt.
- Fixpunkt: Workflow-Ende, Ende einer mittleren Phase und relativer Meilenstein liefern den richtigen Projektstart; Rundweg `startDateFor` → `recalculate` trifft das Datum.
- Auslöser: Dauer ändern, Start ändern, Feiertag anlegen rechnen neu; keine Endlosschleife mit dem Beobachter.
- Altmodell: Ein Projekt mit `schedule_model = 1` behält seine Termine bei Dauer- und Startänderung.
- Migrationsbefehl auf Testdaten: Markteinführung wird Meilenstein (mit und ohne Projektdatum), Druck aktiv, `has_due_date`/`is_start`/`is_end` abgeleitet, ein zweiter Lauf ändert nichts, `--dry-run` schreibt nichts.
- Vorhandene Tests bleiben grün (wegen Standard 1).
