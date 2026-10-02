# Checkliste: Vectory auf einem neuen Zielsystem einrichten

Diese Checkliste gilt für die vollständige Neuinstallation von Vectory. Konkrete Serverpfade, Konten und Sicherungsziele werden je Installation festgelegt und gehören nicht in das Repository.

## Vorbereitungen

- [ ] Installationsmodell festlegen: Einzelorganisation oder mandantenfähiges System
- [ ] unterstützte Versionen von PHP, Datenbank, Composer und Node.js anhand der Projektdateien prüfen
- [ ] Webserver, PHP-Erweiterungen und Datenbank bereitstellen
- [ ] technischen Benutzer sowie Datei- und Verzeichnisrechte festlegen
- [ ] HTTPS-Zertifikat und den endgültigen Hostnamen bereitstellen
- [ ] Backup- und Wiederherstellungsverfahren für Datenbank und hochgeladene Dateien festlegen

## Anwendung

- [ ] Repository auf dem vorgesehenen Stand auschecken
- [ ] `.env` aus `.env.example` anlegen und installationsspezifisch konfigurieren
- [ ] Anwendungsschlüssel erzeugen
- [ ] Datenbankzugang, URL, Zeitzone, Sprache, Mailversand und Dateispeicher konfigurieren
- [ ] PHP-Abhängigkeiten für den Produktivbetrieb installieren
- [ ] Frontend-Abhängigkeiten installieren und Produktionsassets bauen
- [ ] erforderliche beschreibbare Laravel-Verzeichnisse und gegebenenfalls den öffentlichen Storage-Link einrichten
- [ ] Datenbankmigrationen für Vectory ausführen
- [ ] benötigte Ausgangs- und Stammdaten einspielen
- [ ] ersten Super-Admin kontrolliert anlegen
- [ ] Laravel-Caches für den Produktivbetrieb aufbauen

## Scheduler und Hintergrundaufgaben

- [ ] Laravel Scheduler auf dem Zielsystem dauerhaft auslösen
  - Windows: Aufgabe in der **Windows-Aufgabenplanung** einrichten, die jede Minute `php <Vectory-Pfad>\artisan schedule:run` startet
  - Linux: entsprechenden Cron-Eintrag für `php <Vectory-Pfad>/artisan schedule:run` einrichten
- [ ] Ausführung unter dem richtigen technischen Benutzer und mit der produktiven PHP-Version prüfen
- [ ] Protokollierung und Fehlerüberwachung der geplanten Aufgaben prüfen
- [ ] nach Umsetzung des KPr-Bereinigungslaufs einen manuellen Testlauf und anschließend die automatische Ausführung prüfen

Der Scheduler ist für ein kleines persönliches Testsystem derzeit nicht erforderlich. Vor dem produktiven Betrieb muss er eingerichtet werden, sobald Vectory geplante Bereinigungen, Berichte oder andere zeitgesteuerte Aufgaben verwendet.

## Produktivprüfung

- [ ] Anmeldung und Zugriffsstufen mit geeigneten Testkonten prüfen
- [ ] Mandantentrennung beziehungsweise Einzelorganisationsbetrieb prüfen
- [ ] Datei-Upload, Logos und relevante Speicherpfade prüfen
- [ ] Mailversand mit einer Testnachricht prüfen
- [ ] zentrale Projektabläufe und Berechtigungen stichprobenartig prüfen
- [ ] Fehlerseiten ohne Debug-Ausgaben prüfen und Produktionsmodus aktivieren
- [ ] Backup erstellen und eine Wiederherstellung testweise nachvollziehen
- [ ] Anwendung, Queue, Scheduler, Speicherplatz und Backups in die Betriebsüberwachung aufnehmen

## Noch zu konkretisieren

- eingesetztes Queue-Verfahren und dessen dauerhafter Prozess
- produktive Sicherungsintervalle und Aufbewahrungsfristen
- Update- und Rollback-Ablauf für neue Vectory-Versionen
- zentrale Protokollierung und Alarmierungswege
