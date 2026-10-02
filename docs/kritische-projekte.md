# Kritische Projekte

## Ziel des ersten Stands

Die Seite bündelt aktive Projekte, bei denen ein konkret behebbares Problem den weiteren Ablauf gefährdet oder beobachtet werden sollte. Sie zeigt nur Organisationen, auf die der angemeldete Nutzer bereits Zugriff hat.

Direkt vor der Ergebnistabelle steht gemäß den [GUI-Grundsätzen](gui-grundsaetze.md) die Anzahl der aktuell angezeigten kritischen Projekte.
Jede Projektzeile zeigt außerdem das Symbol und den Namen der zugehörigen Organisation.

## Sichtbarkeit

- Super-Admin und Zentral-Admin sehen alle Projekte aller Organisationen der Installation.
- Organisations-Admins sehen alle Projekte ihrer eigenen Organisation.
- Standard-User sehen ohne besonderes Recht nur Projekte, denen ihre Person als Projektbeteiligter zugeordnet ist.
- Das Recht `critical_projects.view_all` erweitert die Sicht eines Standard-Users auf alle Projekte seiner freigegebenen Organisationen. Es erweitert nicht dessen Organisationsgrenzen.

Personenbezogene Befunde zeigen nur die für die Projektsteuerung erforderliche Aussage. Abwesenheitsgründe, Krankheitsdaten und andere nicht benötigte Personaldetails gehören nicht in KPr.

## Dringlichkeitsstufen

- **Handlungsbedarf:** Eine unmittelbar benötigte Voraussetzung fehlt. Der aktuelle Projektschritt kann nicht zuverlässig weitergeführt werden und verlangt sofortige Klärung.
- **Kritisch:** Eine konkrete Abweichung gefährdet Termin, Ablauf oder Budget. Das Projekt sollte zeitnah geprüft und eine Maßnahme festgelegt werden.
- **Beobachten:** Es gibt einen frühen Hinweis oder eine künftig benötigte Angabe fehlt. Noch besteht kein akutes Hindernis, eine Prüfung ist jedoch sinnvoll.

## Regeln

| Code | Regel | Standard-Schwere |
|---|---|---|
| `schedule.overdue` | Ein nicht abgeschlossener Termin liegt vor dem heutigen Datum. | Kritisch |
| `schedule.current_missing` | Bei einem noch nicht abgeschlossenen, terminführenden Workflow-Schritt fehlt das Datum. Start- und Ende-Schritte werden immer geprüft. | Aktueller Schritt: Handlungsbedarf; Start/Ende: Kritisch; andere Schritte: Beobachten |
| `staffing.missing` | Eine im Workflow benötigte Funktionsgruppe hat keine Projektperson. | Beobachten; im aktuellen Schritt: Handlungsbedarf |
| `staffing.person_absent` | Eine Projektperson ist aktuell länger abwesend. Wochenenden und aktive Feiertage ihrer Organisation zählen nicht als Arbeitstage. | 3–5 Arbeitstage: Beobachten; mehr als 5 Arbeitstage: Handlungsbedarf |
| `staffing.person_unavailable` | Eine Projektperson ist inaktiv oder ihr Beschäftigungsende ist erreicht. | Handlungsbedarf |
| `project.start_still_planned` | Der Projektstart ist erreicht, der Status aber weiterhin „Geplant“. | Am Starttag: Beobachten; danach: Kritisch |
| `budget.plan_exceeded` | Gebuchte Stunden überschreiten die wirksamen Planstunden. | Kritisch |

Beendete und verworfene Projekte werden nicht geprüft. Qualitätsmindernde Faktoren werden später separat von Gefährdungen des Projekterfolgs behandelt.

KPr löst aufgrund eines Befunds keinen automatischen E-Mail-Versand aus. Projekt- und Redaktionsleitung tragen die Verantwortung, angezeigte Befunde zu bearbeiten. Ein späterer Mailversand an eine Vertretung wird ausschließlich bewusst durch einen berechtigten Nutzer innerhalb der noch zu entwickelnden Vertretungsfunktion ausgelöst.

## Aufbau

`CriticalProjectEvaluator` ist die gemeinsame fachliche Prüfstelle. Sein Regelkatalog speist zugleich Auswertung, Grundfilter und das sichtbare Regelwerk. Die Seite zählt Projekte eindeutig, auch wenn mehrere Gründe zutreffen. Der Hinweis auf weitere Organisationen berücksichtigt die aktiven Grund- und Schwerefilter.

Die Projektdetails verwenden dieselben Regeln im Fehlercheck. Der Button steht rechts in der Aktionsleiste und wird bei Befunden dezent rot dargestellt. Das Overlay nennt Dringlichkeit, betroffenen Bereich, Befund und mögliche Lösung. Beendete und verworfene Projekte behalten den Button zur konsistenten Bedienung, werden aber nicht mehr geprüft.

## Persönliche Befundsteuerung

Jeder Befund kann persönlich als „Zur Kenntnis genommen“ gekennzeichnet oder bis einschließlich zu einem gewählten Datum ausgeblendet werden. Kenntnisgenommene Befunde bleiben sichtbar und werden optisch zurückgenommen. Ausgeblendete Befunde erscheinen nur mit dem Filter „Ausgeblendete anzeigen“ und können jederzeit wieder eingeblendet werden. Die Kennzeichnungen eines Nutzers haben keine Auswirkung auf andere Nutzer.

Die reine Kenntnisnahme ist installationsweit konfigurierbar und standardmäßig ausgeschaltet. Der Super-Admin kann sie auf der eigenständigen Seite **Superadmin** aktivieren. Das zeitweise Ausblenden bleibt unabhängig von diesem Schalter verfügbar.

Die Datensätze werden erst angelegt, wenn KPr ein aktives Projekt tatsächlich prüft. Sobald eine Ursache bei einer späteren Prüfung nicht mehr besteht, wird das Befundvorkommen einschließlich aller persönlichen Kennzeichnungen gelöscht. Tritt die Ursache danach erneut auf, entsteht ein neues, ungekennzeichnetes Befundvorkommen. Wird ein Projekt beendet oder verworfen, löscht der Statuswechsel sämtliche zugehörigen KPr-Datensätze unmittelbar.

## Spätere Schritte

- Vertretungsfunktion: Nutzer können eine eigene Vertretung festlegen; ein noch festzulegendes Recht erlaubt dies für andere Personen. Eine E-Mail an die Vertretung wird ausschließlich bewusst durch einen Nutzer ausgelöst. Sobald die Vertretungsfunktion besteht, unterdrückt eine hinterlegte Vertretung die Befunde `staffing.person_absent` und `staffing.person_unavailable`.
- Einen täglichen Bereinigungslauf als zusätzliches Sicherheitsnetz vorsehen. Er entfernt verbliebene KPr-Datensätze abgeschlossener, verworfener oder gelöschter Projekte einschließlich der abhängigen Nutzerkennzeichnungen. Der Lauf wird über den Laravel Scheduler definiert; dessen Betrieb ist Bestandteil der [Checkliste für neue Zielsysteme](installation-zielsystem.md).
- tägliche oder wöchentliche E-Mail-Berichte
- weitere Regeln aus Viettos projektbezogenem Fehlercheck fachlich klassifizieren; endgültigen Namen des Checks festlegen
- Schwellenwerte erst nach praktischer Erfahrung konfigurierbar machen
- Info-Mail erst nach deren allgemeiner fachlicher Konzeption anbinden
