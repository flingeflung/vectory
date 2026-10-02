# Kritische Projekte

## Ziel des ersten Stands

Die Seite bündelt aktive Projekte, bei denen ein konkret behebbares Problem den weiteren Ablauf gefährdet oder beobachtet werden sollte. Sie zeigt nur Organisationen, auf die der angemeldete Nutzer bereits Zugriff hat.

Direkt vor der Ergebnistabelle steht gemäß den [GUI-Grundsätzen](gui-grundsaetze.md) die Anzahl der aktuell angezeigten kritischen Projekte.
Jede Projektzeile zeigt außerdem das Symbol und den Namen der zugehörigen Organisation.

## Dringlichkeitsstufen

- **Handlungsbedarf:** Eine unmittelbar benötigte Voraussetzung fehlt. Der aktuelle Projektschritt kann nicht zuverlässig weitergeführt werden und verlangt sofortige Klärung.
- **Kritisch:** Eine konkrete Abweichung gefährdet Termin, Ablauf oder Budget. Das Projekt sollte zeitnah geprüft und eine Maßnahme festgelegt werden.
- **Beobachten:** Es gibt einen frühen Hinweis oder eine künftig benötigte Angabe fehlt. Noch besteht kein akutes Hindernis, eine Prüfung ist jedoch sinnvoll.

## Regeln

| Code | Regel | Standard-Schwere |
|---|---|---|
| `schedule.overdue` | Ein nicht abgeschlossener Termin liegt vor dem heutigen Datum. | Kritisch |
| `schedule.current_missing` | Der aktuelle Workflow-Schritt verlangt einen Termin, hat aber keinen. | Kritisch |
| `staffing.missing` | Eine im Workflow benötigte Funktionsgruppe hat keine Projektperson. | Beobachten; im aktuellen Schritt: Handlungsbedarf |
| `project.start_still_planned` | Der Projektstart ist erreicht, der Status aber weiterhin „Geplant“. | Am Starttag: Beobachten; danach: Kritisch |
| `budget.plan_exceeded` | Gebuchte Stunden überschreiten die wirksamen Planstunden. | Kritisch |

Beendete und verworfene Projekte werden nicht geprüft. Qualitätsmindernde Faktoren werden später separat von Gefährdungen des Projekterfolgs behandelt.

## Aufbau

`CriticalProjectEvaluator` ist die gemeinsame fachliche Prüfstelle. Sein Regelkatalog speist zugleich Auswertung, Grundfilter und das sichtbare Regelwerk. Die Seite zählt Projekte eindeutig, auch wenn mehrere Gründe zutreffen. Der Hinweis auf weitere Organisationen berücksichtigt die aktiven Grund- und Schwerefilter.

## Spätere Schritte

- Rollen und Sichtbarkeit fachlich abschließend festlegen
- Abwesenheit, Beschäftigungsende und ausdrücklich benannte sowie benachrichtigte Vertretung
- „Zur Kenntnis genommen“ und „Ausblenden bis …“
- tägliche oder wöchentliche E-Mail-Berichte
- dieselbe Prüflogik im projektbezogenen Fehlercheck nutzen; endgültigen Namen des Checks festlegen
- Schwellenwerte erst nach praktischer Erfahrung konfigurierbar machen
- Info-Mail erst nach deren allgemeiner fachlicher Konzeption anbinden
