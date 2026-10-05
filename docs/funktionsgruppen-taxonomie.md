# Funktionsgruppen-Taxonomie

## Installationsmodelle

- Ohne Mandantenfähigkeit verwaltet die Organisation ihren eigenen Funktionsgruppen-Katalog.
- Mit Mandantenfähigkeit verwaltet ausschließlich der Heimatdienstleister den gemeinsamen Funktionsgruppen-Katalog.

## Mandantenfähiges System

Funktionsgruppen werden nur beim Heimatmandanten angelegt, benannt, geändert, deaktiviert oder gelöscht. Dieselbe Funktionsgruppen-ID wird organisationsübergreifend in Personen, Workflows, Aufwandsprofile, Projekten, Aufgaben und Planstunden verwendet.

Eine Matrix in **Admin > Personen & Rechte > Funktionsgruppen** legt fest, welche zentralen Funktionsgruppen bei welchem Kunden verfügbar sind. Die Matrix erzeugt keine kundenspezifischen Kopien und erlaubt keine abweichenden Kundenbezeichnungen.

Die Mitgliedschaft einer Person in Funktionsgruppen wird bei ihrer eigenen Organisation gepflegt. Dadurch kann ein Kunde die zentrale Gruppenzuordnung einer für ihn freigegebenen Person des Heimatdienstleisters nicht verändern.

Beim Kopieren eines Workflows zu einem anderen Kunden werden Funktionsgruppen-Zuordnungen nur für Gruppen übernommen, die beim Zielkunden in der Matrix verfügbar sind.
