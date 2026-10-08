# Ablaufplan: Tasks und Meilensteine (Konzeptentwurf)

Stand: 2026-10-08, Entwurf zur Abstimmung. Es ist noch nichts gebaut.

## 1. Ausgangslage

Ein Workflow-Schritt (WFS) trägt heute mehrere Dinge zugleich:

- **Dauer** (`duration_days`, Arbeitstage) und Sperre (`duration_locked`),
- **Termin** (`due_date` am Projektschritt, Name in `milestone_title`, Schalter `has_due_date` am Workflow-Schritt),
- **Markierung** als Projektstart/-ende (`is_start`, `is_end`) und als Markteinführung,
- **Projektstatus** (`lifecycle_status` 1–4: Geplant, In Bearbeitung, Beendet, Verworfen),
- **Erledigt-Zeitpunkte** (`completed_at`, `milestone_done_at`).

Folgen:

- Das Datum neben einem Schritt ist Zeitraum und Termin zugleich. Es ist nirgends erkennbar, ob es den Anfang oder das Ende meint (technisch ist es das Ende).
- „Termine berechnen“ rechnet nur zwischen den Terminen. Der Ablaufplan summiert dagegen alle Schritte. Beide kommen zu verschiedenen Ergebnissen (Beispiel Projekt 260003: 10 AT gegen 49 AT).
- Schritte ohne Arbeit, die nur einen Termin tragen („nur Termin, kein WFS“), sind heute inaktive Workflow-Schritte mit Termin. Sie fehlen in der Workflow-Ansicht als eigene Art und im Ablaufplan als Schritt.

## 2. Zielmodell

### Task (Vorgang)

- Jeder Arbeitsschritt (heute: Schritte „In Bearbeitung“) wird gedanklich zum **Task**: ein Zeitraum mit **Start**, **Ende** und **Dauer** in Arbeitstagen.
- Die Dauer zählt inklusive des Endtags. Ein 1-AT-Task beginnt und endet am selben Tag.
- Ein Task beginnt am nächsten Arbeitstag nach dem Ende des vorigen. Wochenenden und Feiertage der Organisation zählen nicht mit.
- Das Ende eines Tasks bedeutet immer: „Task wurde erfolgreich durchgeführt“ (z. B. „Lektorat ist durchgeführt“).
- Sperre (Schloss) und Voreinstellung der Dauer bleiben wie heute.

### Workflow-Zeitraum

- **Start** des Workflows = Beginn von Task 1, **Ende** = Ende des letzten Tasks. Beides ist berechnet und deckt sich mit Projektstart und -ende.
- Die heutigen Kennzeichen „Projektstart“ und „Projektende“ an einzelnen Schritten entfallen als Bedienung. Sie ergeben sich aus der Kette.

### Meilenstein

- Ein Meilenstein ist ein **Punkt** ohne Dauer und ohne Arbeit, mit Namen und Datum.
- Er kann **beliebig oft** innerhalb von Start und Ende angelegt werden, im Workflow (Vorlage) und im Projekt.
- Er bezieht sich auf genau eine der folgenden Arten:
  1. Abstand vom **Workflow-Start**,
  2. Abstand vom **Workflow-Ende**,
  3. Abstand vom **Start** eines bestimmten Tasks,
  4. Abstand vom **Ende** eines bestimmten Tasks,
  5. **festes Datum** (wandert nicht mit).
- Abstände sind Arbeitstage, vor oder nach dem Bezugspunkt.
- **Ein Meilenstein hängt von Tasks ab, nie umgekehrt.** Verschiebt sich ein Task, wandern abhängige Meilensteine mit. Ein Meilenstein verschiebt nie einen Task.
- **Ein Meilenstein bezieht sich nie auf einen anderen Meilenstein.** Dadurch sind Zirkelbezüge ausgeschlossen.
- Ein Meilenstein ist nur eine Marke und begrenzt keinen Task; der Plan ändert sich dadurch nie. Ein Konflikt (Meilenstein außerhalb von Start und Ende, oder ein Task, der laut Plan nach dem Meilenstein endet, obwohl er davor fertig sein soll) wird als Befund in **Kritische Projekte** gemeldet, nicht im Ablaufplan erzwungen.
- Die heutigen „Termine“ (Redaktionsschluss, Publiziert, Fertig gedruckt, Markteinführung usw.) sind in Zukunft Meilensteine.

### Fixpunkt (Zielscheibe)

- Jedes Datum im Ablaufplan trägt eine kleine Zielscheibe. Ein Klick macht dieses Datum zum Fixpunkt.
- Mögliche Fixpunkte: Workflow-Start, Workflow-Ende, Start oder Ende eines Tasks, ein Meilenstein.
- Wirkung: einmalige Rechenaktion. Die Kette wird so verschoben, dass der gewählte Punkt dieses Datum trifft. Es gibt keinen dauerhaft „festen“ Zustand.
- Das Ergebnis erscheint als Vorschau im Diagramm. Übernommen wird über „Speichern“.
- Mit einem Fixpunkt am Anfang (z. B. festgelegter Entwicklungsstand) rechnet die Kette vorwärts, mit einem Fixpunkt am Ende (z. B. Messetermin) rückwärts, mit einem Fixpunkt in der Mitte nach beiden Seiten.
- Sind Start und Ende beide vorgegeben und passt die Summe der Dauern nicht, gilt wie heute der Hinweis „Es fehlen n AT“. Ob dann Dauern gekürzt oder ein Datum verschoben wird, entscheidet der Mensch.
- Der Knopf „Termine berechnen“ und sein Dialog entfallen. Die Rechnung gibt es nur noch an einer Stelle.

## 3. Trennung von Planung und Steuerung

- **Ablaufplan (Planung):** Soll. Zeiträume, Dauern, Sperren, Meilensteine, Fixpunkt.
- **Workflow-Ansicht (Steuerung):** Ist. Aktueller Schritt, Erledigt-Zeitpunkte, Verantwortliche, Aktionen.
- Beide zeigen dieselben Tasks und Meilensteine und müssen synchron bleiben. Meilensteine sind in beiden pflegbar.
- Soll und Ist werden nie in einem Feld vermischt. Ein Verzug ist der Abstand zwischen Soll-Ende und Ist-Erledigt.

## 4. Oberfläche

- **Ablaufplan unter dem Diagramm:** statt der losen Felderreihe eine **Tabelle** mit Spalten, etwa: Farbe, Name, Sperre, Start, Ende, Dauer (AT), Kalenderwoche, Zielscheibe. Meilensteine stehen als eigene Zeilen oder Rauten an ihrer Stelle.
- **Diagramm:** Balken (Tasks) und Rauten (Meilensteine) lassen sich verschieben. Beim Verschieben eines Meilensteins gelten die Bezugsregeln, ein fest datierter wird neu fixiert.
- **Workflow-Ansicht und Workflow-Verwaltung:** Meilensteine als eigene Art neben den Schritten, mit Bezug (Art und Abstand). Die genaue Darstellung wird gesondert geklärt.

## 5. Betroffene Stellen

- Datenmodell: neue Tabelle für Meilensteine (Workflow-Vorlage und Projekt), Bezug und Abstand, Reihenfolge; die heutigen Felder `due_date`, `milestone_title`, `has_due_date`, `is_start`, `is_end` und der Schalter für inaktive Schritte werden abgelöst.
- Rechnung: `ProjectPlanningCalculator`, `ProjectStepTimeline`, `WorkflowScheduleCalculator` (entfällt in der heutigen Form).
- Weitere Verbraucher der Termine: Kritische Projekte (`CriticalProjectEvaluator`, zusätzlich neue Befunde für Meilenstein-Konflikte), Planung übertragen (Bereich „Termine der Schritte“), Projektkopie-Vorlagen (Bereich Meilensteine), Projektfamilie/Verbund (`ProjectFamilyTimeline`), Illustrationsaufträge, Aktivierung von Schritten, Import aus Vietto, Hilfetexte.
- Oberfläche: Ablaufplan, Terminübersicht, Workflow-Verwaltung, Projektdetails.

## 6. Bestand

- Die Vectory-Projekte der Organisationen **Heimat, Standard und Maschinen** werden in das neue Modell **migriert**. Die Vietto-Testdaten der Organisation Sanitär werden nicht migriert; der neue Code darf sie nicht beschädigen und zeigt sie nach der neuen Rechnung.
- Die Migration überführt die heutigen Termine in Meilensteine und die Arbeitsschritte in Tasks. Es sind erfundene Testprojekte, deshalb muss das Ergebnis grundsätzlich passen, aber nicht auf den Tag genau. Wo ein Termin nicht zur Kette der Dauern passt, bleibt er als Meilenstein mit festem Datum erhalten, und die Kette rechnet aus den Dauern. Ein Konfliktbericht ist nicht nötig.
- Die Workflow-Vorlagen dieser Organisationen werden ebenfalls umgestellt: aus inaktiven „nur Termin“-Schritten werden Meilensteine, aus Terminen an Arbeitsschritten Meilensteine am Ende des Tasks.
- Neue Projekte entstehen ausschließlich im neuen Modell. Eine ausgelieferte Neuinstallation enthält keine Altdaten.

## 7. Umbau in Stufen

1. Konzept abstimmen und festhalten (dieses Dokument).
2. Datenmodell und Rechnung: Tasks, Meilensteine, Kette, Fixpunkt, mit Tests; Migration der Projekte und Vorlagen von Heimat, Standard und Maschinen (ohne Anspruch auf Tagesgenauigkeit).
3. Ablaufplan als Tabelle und Diagramm mit Meilensteinen.
4. Workflow-Verwaltung und Workflow-Ansicht für Meilensteine, Synchronität.
5. Zielscheibe, Wegfall von „Termine berechnen“.
6. Folgestellen (Kritische Projekte, Planung übertragen, Kopiervorlagen, Verbund, Hilfetexte).

## 8. Offene Punkte

1. Darstellung der Meilensteine in der Workflow-Ansicht.
2. Genaue Umwandlungsregel für die inaktiven „nur Termin“-Schritte und für Termine, die nicht zur Kette passen (siehe Abschnitt 6).
3. Genaue Befundregeln für Meilenstein-Konflikte in Kritische Projekte (welche Konflikte, welche Dringlichkeitsstufe); die Meilensteine selbst begrenzen keinen Task.
4. Verhalten der Verbundprojekte (Hauptprojekt und Unterprojekte) mit Fixpunkt und Meilensteinen.
