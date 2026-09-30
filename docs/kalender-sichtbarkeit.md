# Kalender: Sichtbarkeit von Personen und Einträgen

Voraussetzung für jede im Kalender angezeigte Person ist die aktivierte
Personeneinstellung **Kalender**. Kalendereinträge gehören zentral zur Person
und werden nicht je Kunde dupliziert.

## Ohne Mandantenfähigkeit

Alle Kalenderteilnehmer der Nutzerfirma sehen alle anderen Personen, bei denen
**Kalender** aktiviert ist.

## Mit Mandantenfähigkeit

### Angezeigter Kunde: Heimat; Nutzer gehört zu Heimat

- alle Kalenderpersonen aus Heimat
- alle Kalenderpersonen der Kunden, für die der Nutzer freigegeben ist

Die Personen werden nach Unternehmen gruppiert: zuerst Heimat, anschließend die
freigegebenen Kunden. So bleiben die Kunden in der Gesamtübersicht erkennbar.

### Angezeigter Kunde: Kunde; Nutzer gehört zu Heimat

- alle Heimat-Personen, die für diesen Kunden freigegeben sind
- alle Kalenderpersonen dieses Kunden

### Angezeigter Kunde: Kunde; Nutzer gehört zu diesem Kunden

- alle Heimat-Personen, die für diesen Kunden freigegeben sind
- alle Kalenderpersonen dieses Kunden

Die beiden zuletzt genannten Fälle zeigen bewusst denselben gemeinsamen
Arbeitskreis. Personen anderer Kunden sind nicht sichtbar.

## Ereignisarten

- **Abwesenheit**; optional mit einem kurzen Erläuterungstext als Tooltip
- **Mobile-Office**
- **Auswärtstermin**

Die Kategorie Abwesenheit ist bewusst neutral. Medizinische oder andere
sensible persönliche Angaben sind für den gemeinsamen Kalender nicht nötig.

## Bearbeitungsrecht

Jede Kalenderperson darf eigene Einträge anlegen, bearbeiten und löschen.
Einträge anderer sichtbarer Personen dürfen nur mit dem Recht
`calendar.entries.manage_others` (**Kalender: Einträge anderer Personen
bearbeiten und löschen**) geändert oder gelöscht werden. Das Recht erweitert
nicht den sichtbaren Personenkreis.
