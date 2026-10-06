# Übergabe an einen neuen Claude-Chat – Stand 2026-10-06

Lies zuerst `CLAUDE.md` und `MEMORY.md` (Arbeitsregeln, Konventionen, Stolperfallen), danach dieses Blatt. Antworten an Ralf: kurz, Klartext, „du“ im Chat, „Sie“ in UI-Texten, leichter Humor ist erwünscht, keine Fachbegriffe in Zusammenfassungen.

## Wer, was, Ziel
- Ralf (Einzelgründer, Technischer Redakteur) entwickelt **Vectory** (Laravel/Blade/Alpine/Tailwind, MySQL, lokal XAMPP, `D:\htdocs\vectory`) mit dir. Vectory wird sein Produkt.
- **Ziel in 1–2 Wochen: Tester-Stand online.** Offen: Demo-/Testdaten (eigene Demo-Organisation oder Sanitär? echte Personen in der Heimatfirma ersetzen), bereinigte Liefer-DB, Hilfetexte (schreibt Ralf, Entwürfe liegen vor), pptx-Schulung, Zugangslink per Mail (braucht SMTP), möglichst fehlerfrei (**automatischer Rundlauf über alle Seiten × Rollen**), Deployment + HTTPS gemeinsam (kompletter Austausch, Server-.env behalten).
- Mein letzter Vorschlag für den nächsten Schritt: **der automatische Rundlauf**. Ralf hat noch nicht entschieden – frag kurz nach, bevor du loslegst.

## Harte Arbeitsregeln von Ralf
- **Push nur auf sein ausdrückliches „push“.** Commits dürfen laufen. Aktuell ist alles gepusht (`b9c04f1`), Arbeitsstand sauber.
- Keine Subagenten ohne Rückfrage. Nicht ungefragt neue Bausteine beginnen, wenn er gerade testet. Vor dem Bauen kurz bestätigen lassen, was gebaut wird (er findet mich schwer zu bremsen).
- Automatische System-Benachrichtigungen (Hintergrundtask fertig u. ä.) sind **keine** Nutzereingabe.
- Backlog-Artifact (https://claude.ai/artifact/2kMLBruuM5D7EKRJwwdQaz, lokal `…\scratchpad\vectory_backlog.html`) und Roadmap-Memory nach jedem Baustein von selbst nachziehen (nur fertige Funktionen und offene Todos; Fehlerbehebungen gehören ins Roadmap-Memory). Aktuell Version 49.
- UI-Begriffe: **Organisation** (nicht Kunde/Mandant), **Aufwandsprofil** (früher Aufwandsschablone), **Planstunden je Funktionsgruppe**, **Einzelprojekt** (Projekt ohne Verbund), **Glossar-Links** (früher „Begriffe“), „Speichern“ als einheitlicher Button.
- Hilfetexte und Hinweise als „**Wenn …, dann …**“ formulieren (Kausalität), Sie-Form.
- Ralf „stoppt“ nur, wenn er es selbst schreibt.

## Technische Stolperfallen
- Tools: Bash-Heredocs mit Backslashes (PHP-Namespaces) werden verstümmelt → Skripte mit dem Write-Tool anlegen; Python-Ersetzungen mit Rückwärtsschrägstrichen vorsichtig.
- **Nie zwei phpunit-Läufe gleichzeitig** (gleiche Test-DB, sonst Phantomfehler).
- Neue Tailwind-Klassen brauchen `npm run build`. Blade: kein `@php … @endphp` Block, wenn irgendwo davor ein inline `@php(…)` steht; Alpine: keine doppelten Anführungszeichen in x-data/@click.
- Nach neuen `__()`-Texten `php artisan lang:sync en`, veraltete Schlüssel in `lang/en.json` von Hand entfernen.
- Admin-Seiten/Rechte: `OrganizationGuardTest` (rohe `DB::table`-Abfragen auf Tenant-Tabellen mit Begründung in `REVIEWED_RAW_QUERIES` eintragen).
- Im Browser testen nur mit Testkonto; es gibt keinen bekannten Admin-Zugang (qa-test ist „user“). Tinker-Render als Super-Admin geht immer.

## Was seit dem letzten Handover gebaut wurde (alles gepusht)
- **Kopierseite „Konfiguration übernehmen“**, Löschen von Test-Organisationen, Wächtertests.
- **Einsatzplan** am Workflow, **Auslastung** im Projekt (Planung › Auslastung), gemerkte Wochenwahl in Zeiten › Personen & Tage, Zeitverlauf-Achse aus Projektdaten + Buchungen.
- **Hilfe-System:** Hilfe je Reiter (`D-ID#reiter`, `config/help-tabs.php`, `data-help-tab`), vier Ebenen, Brotkrumenpfad, Klappbaum, Rechtsklick „darunter einfügen“, Verweise per Hilfe-Nr. (`[[42]]`), Glossar-Links (`((Begriff))`, Verwaltung unter Admin › Kommunikation › **Glossar-Links**), Freigabe-Häkchen (Schloss), Vietto-Schild = nur für Admins sichtbar, Editor mit Textmarker-Symbolen hinter jedem Markup. Für den Super-Admin zeigt das Hilfe-Panel den **Schlüssel** und „**Hauptseite sichtbar für**“ (`config/help-access.php`, aus dem Code ausgewertet – bei Rechteänderungen mitpflegen, Test prüft Rechte-Namen).
- Aufwandsprofil als Spalte und Filter in der Projektübersicht; Produktgruppen gruppiert bei den Modellen; Kalenderwoche hinter jedem Datumsfeld (zentrales Skript in `layouts/app.blade.php`; die Browser-Datumsauswahl selbst kann keine KW zeigen – bewusst so gelassen, keine Bibliothek).
- 25+ Entwurfs-Hilfeseiten für alle Navigationspunkte (**nicht freigegeben**, nur in der Entwicklungs-DB).

## Offene Punkte / Entscheidungen
- **Entwurfs-Hilfeseiten sind für Tester sichtbar**, das Schloss blendet nichts aus. Entscheiden: ausblenden oder vor Auslieferung entfernen/freigeben. Hilfeseiten, Glossar-Links und Zugriffstexte müssen in die **Liefer-DB**.
- Planung › Arbeitszeit: Jahre bis 2036 anbieten? (angeboten, nicht entschieden)
- Sperre für deaktivierte Personen (Login/Sitzung) – Lücke bekannt, verschoben.
- Lasttest Planung mit vielen künstlichen Daten – verschoben.
- Planungsseiten prüfen das Recht uneinheitlich (Start/Projektplanung: auch Funktionsgruppen-Mitglieder; Stunden/Arbeitszeit/Grundlast: nur `planning.view`). Soll das so bleiben?
- Dürfen Admins fremde Zeitbuchungen im Projekt korrigieren/löschen? Bisher nein (nur eigene).
- Rechtsklick-Kontextmenü: Ralf wollte „erst mal grundsätzlich wissen, ob es geht“ (ja, auch am Mac per Zwei-Finger-Tipp/Ctrl+Klick); Inhalt und Ort wollte er noch erklären. Nichts gebaut.
- „Auf Halde“ (bewusst später): weitere Regeln für Kritische Projekte/Fehlercheck, Mail-Funktionen am Workflow-Schritt + Alarm-Mail, Aus-/Einchecken (nur Platzhalter-Knopf), Stellvertreter, Gast-Rolle, Team-Konzept. Ein Bereich „Auf Halde“ im Backlog kommt erst nach gemeinsamem Sortieren.
- Begriffs-Sweep „Kunde/Mandant“ → „Organisation“ in der übrigen Oberfläche: nicht vollständig gemacht.
- Ralf schreibt gerade selbst Hilfetexte (u. a. Planung › Stunden); Formulierungen als „Wenn …, dann …“.
