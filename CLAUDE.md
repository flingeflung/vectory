# Vectory – Projekt-Kontext

## Ausgangslage
- Vectory ist die Neuentwicklung/Modernisierung von Vietto (internes PHP/MySQL-Tool). Referenz: D:\htdocs\vietto (siehe dortige agents.md für Umgangsregeln – nie verändern, nur lesend analysieren).
- Ralf hat Vietto selbst entwickelt und kennt es im Detail. Er gibt fachlich vor, was gebaut wird; Umsetzung liegt bei Claude, Vorschläge sind willkommen.
- One-Man-Show, kein Team.

## Vorgehen
- Step 1 (Ziel: in 1–2 Wochen etwas Vorzeigbares): stakeholder-taugliche Demo mit den wichtigsten Funktionen.
- Build-Reihenfolge Step 1: 1. Login/Auth, 2. Startseite als konfigurierbares Dashboard (wie in Vietto, ggf. mit neuen/anderen Funktionen), 3. Projektübersicht.
- Step 2: schrittweise Erweiterung um weitere Funktionen.

## Tech-Stack
- Laravel (PHP), Composer + npm/Vite.
- Für Login/Auth: Breeze als Ausgangsbasis erwägen.
- Eigene, von Vietto komplett getrennte Datenbank `vectory` (bereits angelegt: utf8mb4_unicode_ci, leer, lokal via XAMPP/MySQL, root ohne Passwort wie bei Vietto).

## Mandantenfähigkeit
- Langfristziel: marktfähiges Produkt für zwei Zielgruppen – (1) interne TR-Abteilungen in Unternehmen (keine Mandantenfähigkeit nötig) und (2) Dienstleister für TR mit vielen Kunden (Mandantenfähigkeit nötig).
- Entscheidung: Mandantenfähigkeit von Anfang an im Datenmodell verankern (Single-DB-Ansatz: `tenant_id`-Spalte + globaler Eloquent-Scope), aber Mandanten-Verwaltungs-UI/Umschalten erst später bauen. Ein Default-Mandant reicht für Step 1.

## Rollenmodell
1. Super-Admin – verwaltet auch die Mandantenfähigkeit
2. Admin – Admin-Tätigkeiten pro Mandant/Unternehmen
3. User – loggt sich ein, arbeitet im Tool
4. Kontaktperson – kein Login, nur Stammdaten (z. B. für E-Mail-Benachrichtigungen zu Projektfortschritt), kann später auf Rolle 3 hochgestuft werden

Umsetzungsidee: Rolle 4 als eigene `person`-Entität modellieren, `user` referenziert optional eine `person`. Upgrade auf Rolle 3 = zusätzlicher `user`-Datensatz, keine Datenmigration nötig.

## Erkenntnisse aus Vietto-Analyse (nur Referenz, nicht unkritisch übernehmen)
- `personen.intTyp` unterscheidet bereits 1=Login-User, 2=E-Mail-Kontakt, 99=automatischer Prozess – bestätigt die Trennung Rolle 3/4.
- `firmen`-Tabelle ist nur ein Firmen-Tag an Personen (Viega + externe Dienstleister), keine echte Mandantentrennung – Mandantenfähigkeit muss in Vectory komplett neu gebaut werden.
- `rollen` in Vietto sind fachliche Funktionen (Admin/TR/PM-PT/ÜS-Mgmt/Gast/Prozess) – andere Achse als das neue Zugriffsrollen-Modell oben, nicht 1:1 übernehmen.
- Vietto-Rechtesystem (`rechte`, `rechte_cx`, `navigation.blnActive`) ist granular, aber Alt-typisch gewachsen – fachlich neu bewerten statt kopieren.

## UI-Konventionen

- **Speichern-Button statt Sofort-Speichern**: Formulare mit mehreren/größeren Feldern (z. B. Illustrationsauftrag anlegen, Status ändern) speichern erst über einen expliziten Button, nicht bei jeder Änderung automatisch. Ausnahme: reine Anzeige-/Filtersteuerung (Dropdowns, Checkboxen für Filter/Sichtbarkeit) darf per `onchange` sofort submitten – die ändert keine Daten, nur die Ansicht.
- **Sicherheitsabfrage bei ungespeicherten Änderungen**: Jeder Dialog mit einem Speichern-Button (siehe oben) muss beim Schließen (X, Escape, "Schließen"-Button) nachfragen, wenn etwas geändert, aber nicht gespeichert wurde. Umsetzung: `x-modal`-Komponente mit `:dirty-check="'irgendeineWindowFunktion'"`; die Funktion vergleicht die aktuell serialisierten Formulare gegen einen beim Laden/nach dem Speichern erstellten Snapshot. Beispiele: `projectOverlayIsDirty` (Projekt-Overlay), `illustrationOrdersIsDirty` (Illustrationsaufträge-Modal), siehe `resources/views/layouts/app.blade.php`.
- **Inaktive Elemente in Auswahllisten**: graue Schrift (`text-gray-400`) + Suffix " [i]" am Namen (z. B. `{{ $x->name }}{{ ! $x->active ? ' [i]' : '' }}`, Option/Label mit `@class(['text-gray-400' => ! $x->active])`). Gilt für alle Personen-/Workflow-Auswahllisten im Projekt. Bei großen Listen (z. B. Aufgaben-Personenfilter) inaktive standardmäßig ausblenden, mit einer eigenen Checkbox einblendbar machen statt immer anzuzeigen.
- **Klickbare Aktionen sehen wie Buttons aus, nicht wie Text-Links**: für Aktions-Trigger (z. B. "verwalten", "ändern") immer den kleinen Sekundär-Button-Stil verwenden (`inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200`), keine reinen blauen Text-Links (`text-indigo-600 hover:underline` o. ä.). Ralfs Begründung: "Was klickbar ist, sollte auch so aussehen." Betrifft neu gebaute Stellen; bestehende Text-Links (z. B. PN-Links in Tabellen) werden erst bei Gelegenheit nachgezogen, nicht automatisch.
- **Filter lösen automatisch aus**: Pulldowns in einem Filterbereich lösen die Filterung sofort per `onchange="this.form.submit()"` aus, kein separater "Filtern"-Button nötig. Textfelder (z. B. Namenssuche) lösen debounced 1-2 s nach dem letzten Tastendruck aus (`oninput`, `clearTimeout`/`setTimeout`-Muster). Gilt für serverseitige Filter-Formulare (GET), nicht zu verwechseln mit rein clientseitigen Alpine-Filtern (die schon immer sofort reagieren). Beispiel: `resources/views/admin/personen/index.blade.php`.
- **Kürzel statt Vollname in breiten/engen Listen**: wo der volle Personenname zu viel Platz wegnimmt (breite Tabellen mit vielen Spalten, oder umgekehrt sehr schmale Spalten), das Kürzel (`Person.short_name`) statt Vor-/Nachname anzeigen, dabei immer `title="{{ $person->fullName() }}"` als Tooltip. Beispiel: Kürzel-Spalte in der Personenverwaltung (`resources/views/admin/personen/index.blade.php`).
- **Boxen mit Kopf-/Fußbereich + Liste**: bei jeder Box, die einen festen Kopfbereich (Titel, Suche, Filter, Buttons) und/oder Fußbereich (z. B. Speichern-Button) plus eine potenziell lange Liste/Inhalt kombiniert, bleiben Kopf und Fuß fix stehen (`shrink-0`) - nur der eigentliche Inhalt dazwischen scrollt (`flex-1 min-h-0 overflow-y-auto`). Nie die ganze Box inklusive Kopf/Fuß scrollen lassen. Beispiel: Rechte-Sets- und Personen-Boxen in der Rechte-Verwaltung (`resources/views/admin/rechte/index.blade.php`).

## Kommunikationsstil
- Ralf möchte kurze, prägnante Antworten ohne ausschweifende Erklärungen ("keine Romane").
