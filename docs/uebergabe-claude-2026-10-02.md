# Übergabe an Claude – Arbeitsstand 02.10.2026

## Zweck und Stand

Dieses Dokument fasst die fachlichen Entscheidungen und Implementierungen der langen gemeinsamen Vectory-Session zusammen. Es dient als Ausgangspunkt für Architekturprüfung, Review und weitere Aufgabenplanung durch Claude.

Aktueller Git-Stand bei Erstellung: `17eea2c Add project person availability checks` auf `main`.

## Begriffe

- **Planungsseite:** Hauptnavigationspunkt **Planung**.
- **Planungstab:** Tab **Planung** innerhalb der Projektdetails.
- Im GUI wird **Tab** statt „Reiter“ verwendet.
- **KPr:** Hauptnavigationspunkt **Kritische Projekte**.
- **Funktionsgruppe:** zentral durch die Heimat- beziehungsweise zentrale Organisation gepflegte fachliche Taxonomie.
- **Super-Admin (S-A):** technischer Vollzugriff auf die gesamte Installation.
- **Zentral-Admin (Z-A):** vollständige fachliche Verwaltung aller Organisationen einer Installation.
- **Organisations-Admin (O-A):** vollständige fachliche Verwaltung der eigenen Organisation.
- **Standard-User:** fachliche Rechte ausschließlich über ein Rechte-Set.
- Fachliche Personenrollen und Funktionsgruppen sind von Zugriffsstufen und Rechte-Sets getrennt.

## Zugriffsstufen und Rechte

Die drei Admin-Stufen sind systemseitig getrennt:

| Zugriffsstufe | Organisationsumfang | Aufgabe |
|---|---|---|
| Super-Admin | gesamte Installation | Technik, Support, Fehlersuche, installationsweite Einstellungen |
| Zentral-Admin | gesamte Installation | fachliche Administration und Ressourcensteuerung |
| Organisations-Admin | eigene Organisation | fachliche Administration innerhalb der Organisation |
| Standard-User | freigegebene Organisationen | Rechte aus genau einem Rechte-Set einschließlich Basis und Bausteinen |

Super- und Zentral-Admin unterscheiden sich bei fachlichen Gesamtübersichten wie KPr nicht im sichtbaren Projektumfang. Der Unterschied liegt in technischen beziehungsweise installationsweiten Funktionen.

Die Dokumentation liegt in `docs/zugriffsstufen-und-rechte.md`.

### Planung

- Die Planungsseite ist grundsätzlich für User mit Login und fachlicher Teilnahme verfügbar.
- Ohne `planning.view` ist nur **Projektplanung** sichtbar, beschränkt auf die eigene Person und eigene Projekte.
- `planning.view` schaltet Stunden, beide Grundlast-Tabs, Arbeitszeit sowie personenbezogene Auswertungen frei.
- Im Planungstab eines Projekts sehen User ohne `planning.view` nur die Planstunden je Funktionsgruppe, ohne Lösen oder Verteilung.
- Im Zeiten-Tab bleiben aggregierte Projektstunden sichtbar; personenbezogene Auswertungen benötigen `planning.view`.

### Kritische Projekte

- Super- und Zentral-Admin sehen alle Projekte aller Organisationen.
- Organisations-Admins sehen alle Projekte ihrer eigenen Organisation.
- Standard-User sehen Projekte, denen ihre Person zugeordnet ist.
- Das neue Recht `critical_projects.view_all` erweitert Standard-User auf alle Projekte ihrer freigegebenen Organisationen, aber niemals über deren Organisationsgrenzen hinaus.
- Die gemeinsame Zugriffskapselung liegt in `app/Support/CriticalProjectAccess.php` und gilt für Übersicht, Projektöffnung und persönliche Befundaktionen.

## Mandantenfähige Funktionsgruppen

- Funktionsgruppen werden zentral bei der Heimat- beziehungsweise zentralen Organisation gepflegt.
- Eine Matrix steuert ihre Verfügbarkeit bei Kundenorganisationen.
- Projektbeteiligte Funktionsgruppen stammen nicht mehr aus kundenspezifischen Taxonomien.
- Die Sichtbarkeit im Projekt ist grundsätzlich von Aufwandsschablone und Workflow unabhängig; der Workflow markiert lediglich fachlich relevante Gruppen.
- Das Flag für Illustrationsfunktionsgruppen bleibt erhalten, weil es den Navigationspunkt Illustrationen steuert.
- Im Admin-Bereich beschreibt der Hinweis die gültige Organisation und verweist bei Kunden auf die zentrale Konfiguration.

Details: `docs/funktionsgruppen-taxonomie.md`.

## Projektplanung und Arbeitszeit

Umgesetzt wurden unter anderem:

- Tabreihenfolge mit **Projektplanung** zuerst.
- Personen- und Organisationsfilter mit Submit-Lock gegen Mehrfachklicks.
- Monats- und Jahresauswahl als Dropdowns.
- Ladeanzeige für Ansichtsumschalter.
- Projektanzahl in Übersichten.
- Organisationslogos in mandantenübergreifenden Projektlisten.
- Projektlinks öffnen Projektdetails im Overlay.
- Gantt-Fortsetzungskennzeichnung für Projekte außerhalb des sichtbaren Zeitraums.
- Meilensteine als Rauten und geplante Stunden am Projektbalken.
- Dezenter Hinweis bei Projekten ohne Workflow.
- Gemeinsame Arbeitszeitansicht für alle Personen mit stabiler Farbpalette und mindestens zwei Stunden Luft oberhalb des höchsten Wertes.
- Der zuletzt verwendete Planungstab wird als persönliche Einstellung gespeichert.

Als Leistungsrisiko wurde festgehalten, dass große Kombinationen aus Personen und Organisationen die Projektplanung langsam machen können. Eine Optimierung der Abfragen beziehungsweise Voraggregation liegt im Backlog.

## Planstunden und Zeiten in Projekten

- Bearbeitung und Verteilung der Planstunden wurden in den Planungstab verschoben.
- Der Verteilen-Button besitzt eine Sicherheitsabfrage, speichert anschließend automatisch und zeigt grünes Erfolgsfeedback.
- Übersichten wurden kompakter gestaltet; Überschrift, Aktionen und Summe bleiben stehen, nur die Funktionsgruppen scrollen.
- Im Zeiten-Tab gibt es eine reduzierte Soll-Ist-Sicht.
- Die frühere Anteilsspalte wurde entfernt.
- Ein Soll-Ist-Balkendiagramm je Projekt wurde ergänzt; Überbuchungen werden optisch markiert.
- Die Texte **Projektstunden**, **Personen & Tage** und **Zeitverlauf** ersetzen ältere unklare Benennungen.
- Hinweise erklären bei Hauptprojekten den Bezug auf Haupt- und Unterprojekte.

## Organisationen und Logos

- Der Admin-Tab heißt **Organisationen**.
- Organisationslogos werden per normaler Dateiauswahl hochgeladen, nach `public/images/company-icons` kopiert und über den gespeicherten Dateinamen mit der Organisation verbunden.
- Die Logos werden bereits in der Projektplanung, in KPr und in Projektdetails bei mandantenfähigen Installationen verwendet.

## Workflow und Termine

- Kopierte oder historische Projekte dürfen keine abgeleiteten Start- oder Enddaten anzeigen, wenn der referenzierte Workflow-Schritt kein Datum besitzt.
- Workflow-Konfigurationen werden plausibilisiert:
  - Start- oder Endschritte müssen „Hat Termin“ besitzen.
  - Konkreter Lösungshinweis: Checkbox **Hat Termin** markieren.
  - Es darf höchstens einen als Projektstart und höchstens einen als Projektende markierten Termin geben.
- Für alle terminführenden Projektschritte wird ein fehlendes Datum geprüft.
- Die Terminberechnung liefert keine rohe Laravel-Ausnahme mehr, sondern einen kontrollierten Befund.

## Fehlercheck in den Projektdetails

- Oben rechts existiert ein Button **Fehlercheck**.
- Bei Befunden erscheint er dezent rot.
- Beendete und verworfene Projekte behalten den Button, werden aber nicht mehr geprüft.
- KPr und Fehlercheck verwenden dieselbe Prüflogik (`CriticalProjectEvaluator`), aber unterschiedliche Darstellungen:
  - KPr ist die projektübergreifende Steuerungsübersicht.
  - Fehlercheck zeigt den vollständigen Zustand eines einzelnen Projekts.
- Der Name „Fehlercheck“ bleibt vorerst bestehen. Eine spätere Namensprüfung steht im Backlog.

## Kritische Projekte – aktueller Funktionsumfang

Zentrale Dokumentation: `docs/kritische-projekte.md`.

### Oberfläche

- Anzahl der angezeigten kritischen Projekte steht direkt vor der Tabelle.
- Filter nach Dringlichkeit, Grund und Organisation wirken sofort ohne separaten Filtern-Button.
- Es gibt **Filter zurücksetzen** sowie **Alle/Keiner** in der Organisationsauswahl.
- Die Organisationsauswahl von KPr ist unabhängig vom globalen Organisationsschalter.
- Ein Hinweis nennt nur die Anzahl weiterer kritischer Projekte in anderen Organisationen.
- Der Regelwerk-Button steht abgesetzt rechts und ist hellblau gestaltet.
- Projektzeilen enthalten PN, Bezeichnung, Workflow, aktuellen Schritt, Status, Projektbeteiligte, Organisationssymbol und Befunde.
- Beobachten-Befunde werden in der Übersicht pro Projekt zusammengefasst.
- Lösungshinweise und Aktionen liegen im Detail-Overlay.
- Befunde sind je Projekt nach Dringlichkeit sortiert.
- Die Begriffe lauten **Handlungsbedarf**, **Kritisch** und **Beobachten**.
- „Nicht berücksichtigt“ und „Mögliche Lösung“ ersetzen missverständliche ältere Formulierungen.

### Derzeitige Regeln

| Code | Regel | Einstufung |
|---|---|---|
| `schedule.overdue` | Termin überschritten | Kritisch |
| `schedule.current_missing` | Termin im Workflow-Schritt fehlt | aktueller Schritt: Handlungsbedarf; Start/Ende: Kritisch; sonst Beobachten |
| `staffing.missing` | Erforderliche Funktionsgruppe nicht besetzt | aktueller Schritt: Handlungsbedarf; sonst Beobachten |
| `project.start_still_planned` | Start erreicht, Projekt weiterhin geplant | Starttag Beobachten, danach Kritisch |
| `budget.plan_exceeded` | Gebuchte Stunden über Planstunden | Kritisch |
| `staffing.person_absent` | Projektperson aktuell länger abwesend | 3–5 Arbeitstage Beobachten; mehr als 5 Arbeitstage Handlungsbedarf |
| `staffing.person_unavailable` | Projektperson inaktiv oder Beschäftigungsende erreicht | Handlungsbedarf |

Bei Abwesenheiten zählen Wochenenden und aktive Feiertage aus der Heimatorganisation der Person nicht als Arbeitstage. Abwesenheiten von höchstens zwei Arbeitstagen erzeugen keinen Befund. Derzeit werden nur aktuell laufende Abwesenheiten geprüft.

### Persönliches Ausblenden und optionale Kenntnisnahme

- Persönliche Zustände liegen in `critical_project_findings` und `critical_project_finding_states`.
- Ein Befund kann bis einschließlich eines Datums ausgeblendet werden.
- Nach Ablauf erscheint er am Folgetag wieder, solange die Ursache besteht.
- Ausblendungen wirken nur in KPr und nur für den betreffenden Nutzer. Der Fehlercheck bleibt vollständig.
- Die Übersicht kann Projekte mit ausschließlich ausgeblendeten Befunden über **Ausgeblendete anzeigen** wieder einblenden.
- Projekte mit teilweise ausgeblendeten Befunden tragen eine Mengenkennzeichnung.
- Die Checkbox verändert die Sortierung nicht; die fachliche Priorität berücksichtigt weiterhin alle zum Filter passenden Befunde.
- Wird die Ursache beseitigt, werden Befundvorkommen und persönliche Zustände gelöscht.
- Bei Beendet oder Verworfen werden sämtliche KPr-Datensätze des Projekts sofort gelöscht.
- Bei späterer Reaktivierung entstehen bestehende Ursachen als neue, ungekennzeichnete Befunde.
- Kenntnisnahme ist installationsweit per Superadmin-Schalter aktivierbar und standardmäßig ausgeschaltet. Die Datenstruktur bleibt erhalten.
- Aktionen im Detail-Overlay schließen das Overlay nicht.

### E-Mail- und Vertretungsentscheidung

- KPr verschickt aufgrund eines Befunds keine E-Mails.
- Projekt- und Redaktionsleitung haben eine Holschuld und müssen auf Befunde reagieren.
- Eine spätere Vertretungsfunktion erlaubt Nutzern, eine eigene Vertretung festzulegen.
- Ein noch festzulegendes Recht erlaubt das Festlegen einer Vertretung für andere Personen.
- In diesem zweiten Fall kann der handelnde Mensch bewusst eine E-Mail an die Vertretung senden.
- Vollautomatische Vertretungsmails sind ausdrücklich zurückgestellt, weil Auslösezeitpunkt und Seiteneffekte derzeit nicht zuverlässig beherrschbar sind.
- Sobald Vertretungen existieren, unterdrückt eine gültige Vertretung die Abwesenheits- und Nichtverfügbarkeitsbefunde.

### Vorgeschlagene nächste KPr-Regeln

Diese Vorschläge wurden fachlich positiv aufgenommen und sollen als nächste Kandidaten erhalten bleiben:

1. **Projektende überschritten, Projekt weiterhin geplant oder in Bearbeitung** – Kritisch.
2. **Projekt in Bearbeitung, aber kein aktueller Workflow-Schritt vorhanden** – Handlungsbedarf.
3. **Mehrere Workflow-Schritte gleichzeitig aktuell** – Handlungsbedarf.
4. **Alle Workflow-Schritte abgeschlossen, Projekt weiterhin in Bearbeitung** – Beobachten.
5. **Aktueller Workflow-Schritt benötigt eine Funktionsgruppe, aber keine verfügbare Person ist vorhanden** – Handlungsbedarf; bestehende Besetzungsprüfung entsprechend präzisieren.

Weitere mögliche Regeln mit höherem Klärungsbedarf:

- aktives Projekt ohne Workflow,
- aktives Projekt ohne Planstunden,
- gebuchte Stunden ohne Planstunden,
- Planstunden fast ausgeschöpft,
- Projektende nähert sich bei deutlich zurückliegendem Workflow,
- längere Zeit ohne Projektaktivität.

Konfigurationsinkonsistenzen wie Start nach Ende oder beschädigte Workflow-Referenzen gehören primär in den Fehlercheck und nur bei konkreter Gefährdung zusätzlich in KPr.

## GUI-Grundsätze aus der Session

- Listen beginnen mit einer kontextbezogenen Anzahl, beispielsweise „9 kritische Projekte“, nicht mit „Datensätze“.
- Listen mit Daten verschiedener Organisationen zeigen immer das Organisationssymbol.
- Buttons mit potenziell langer Verarbeitung erhalten unmittelbar Submit-Lock, Ladeanzeige und deaktivierten Zustand.
- Kopf- und Fußbereiche von langen Dialogen bleiben stehen; nur der eigentliche Inhalt scrollt.
- Tabellenübersichten sollen kompakt bleiben; Detailaktionen gehören bei Bedarf in Overlays.
- Projektlinks öffnen das Projektdetails-Overlay und keine vollständige Hauptseite.

## Topbar-Schnelllisten

- Favoriten wurden aus der Hauptnavigation entfernt.
- Links vom Organisationsschalter steht eine gemeinsame Icon-Gruppe:
  - Stern: Favoriten,
  - Uhr mit Rücklaufpfeil: zuletzt geöffnete Projekte.
- Beide öffnen kleine globale Overlays und von dort das Projektdetails-Overlay.
- Die bestehende Tabelle `recently_viewed_projects` bleibt die einzige Datenquelle und enthält maximal 15 Projekte pro Nutzer.

## Superadmin

- Superadmin ist kein Admin-Tab mehr, sondern ein eigener Navigationspunkt ganz unten.
- Alte gespeicherte Admin-Ziele, die noch auf Superadmin verwiesen, werden bereinigt.
- Der installationsweite Schalter für die KPr-Kenntnisnahme liegt dort.

## Noch offene technische Arbeiten

- Vertretungsfunktion einschließlich neuem Recht und bewusst ausgelöstem Mailversand konzipieren.
- Täglichen Scheduler-Bereinigungslauf für verwaiste KPr-Datensätze ergänzen. Für lokale Tests ist kein Scheduler erforderlich; die Zielsystem-Checkliste enthält den späteren Betriebsbedarf.
- KPr-E-Mail-Reports täglich oder wöchentlich erst später konzipieren. Diese Reports sind von automatischen Vertretungsmails zu unterscheiden.
- Performance der Projektplanung bei vielen Personen und Organisationen optimieren.
- Weitere KPr-Regeln schrittweise ergänzen und nach jedem Schritt Regelwerk und Dokumentation aktualisieren.
- Info-Mail-Funktion erst nach allgemeiner, von Print-Anleitungen unabhängiger Fachkonzeption anbinden.

## Relevante jüngste Commits

- `17eea2c` Abwesenheit, Inaktivität und Beschäftigungsende in KPr
- `829356b` Favoriten und zuletzt geöffnete Projekte in die Topbar verschoben
- `957ab13` KPr-Sichtbarkeit nach Zugriffsstufe, Projektzuordnung und neuem Recht
- `640ecae`, `e34e7c0`, `b49b7d9` Filterung und stabile Sortierung ausgeblendeter Befunde
- `65e8d0f` sichere mandantenübergreifende Projektöffnung aus KPr
- `a6b3b52` bis `2460a51` persönliche Befundsteuerung und kompakte Detaildarstellung
- `8f42027`, `a017f0f` Fehlercheck in Projektdetails
- `bb437cb`, `200c997`, `5124e8f`, `0a505b1` Workflow-Terminprüfungen

## Hinweise für Review und Weiterarbeit

- Vietto bleibt strikt schreibgeschützte Referenz.
- Mandantenübergreifende Abfragen dürfen globale Scopes nur lokal und begründet umgehen.
- Eine frühere systemweite Scope-Aufweichung für KPr wurde verworfen; die aktuelle Lösung arbeitet mit gezielten ungescopten Relationen ausschließlich in der KPr-Abfrage.
- Neue KPr-Regeln gehören zentral in `CriticalProjectEvaluator::definitions()` und `evaluate()`, damit KPr, Grundfilter, Regelwerk und Fehlercheck konsistent bleiben.
- Nach jeder neuen Regel müssen Dringlichkeit, Nichtberücksichtigung und mögliche Lösung dokumentiert und getestet werden.
