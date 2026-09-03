# AGENTS.md

## Rolle

Du arbeitest als Implementierer und technischer Analyst.
ChatGPT übernimmt Architektur, Review und Aufgabenplanung.
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

Git-Aktionen wie Commit, Branch, Merge, Rebase, Tag, Push oder Pull ausschließlich nach ausdrücklicher Anweisung.

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

## Abschlussbericht

Sofern nicht anders gefordert, berichte ausschließlich:

1. Geänderte oder analysierte Dateien
2. Ausgeführte Befehle
3. Testergebnisse oder Analyseergebnis
4. Offene Punkte und fachliche Annahmen
