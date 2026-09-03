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

## Kommunikationsstil
- Ralf möchte kurze, prägnante Antworten ohne ausschweifende Erklärungen ("keine Romane").
