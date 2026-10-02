# Zugriffsstufen, Organisationsgrenzen und Rechte

Diese Datei ist die verbindliche fachliche und technische Referenz für alle künftigen Erweiterungen von Vectory. Bei widersprüchlichen älteren Kommentaren oder Notizen gilt diese Beschreibung.

## 1. Fünf getrennte Ebenen

Vectory unterscheidet:

1. **Zugriffsstufe**: systemseitig festgelegter administrativer Rahmen eines Logins.
2. **Organisationsbereich**: Organisationen, deren Daten grundsätzlich erreichbar sind.
3. **Rechte-Set**: konkrete fachliche Funktionen eines Standard-Users innerhalb seines zulässigen Bereichs.
4. **Fachliche Rolle**: Tätigkeit einer Person, beispielsweise Technische Redaktion oder Projektleitung.
5. **Teilnahmeattribute**: beispielsweise Ressourcenplanung und Kalender.

Diese Ebenen dürfen nicht vermischt werden. Eine fachliche Rolle oder ein Teilnahmeattribut verleiht keine Rechte. Ein Rechte-Set verleiht keine Admin-Zugriffsstufe und erweitert keine Organisationsgrenze.

## 2. Übersicht der Zugriffsstufen

| Zugriffsstufe | Typischer Nutzer | Datensicht | Administration | Rechte-Set |
|---|---|---|---|---|
| **Super-Admin** | Entwickler oder technischer Support von Vectory | gesamte Installation | fachliche und technische Funktionen, Sicherheitsausnahmen | nicht erforderlich |
| **Zentral-Admin** | Redaktions- oder Teamleitung der zentralen Organisation | alle operativen Daten aller Organisationen | vollständige fachliche Administration aller Organisationen | nicht erforderlich |
| **Organisations-Admin** | verantwortliche Person einer Kundenorganisation | ausschließlich Daten der eigenen Organisation | vollständige fachliche Administration der eigenen Organisation | nicht erforderlich |
| **User** | regulärer Anwender mit Login | eigene sowie ausdrücklich freigegebene Bereiche | nur über konkrete Benutzerrechte | bestimmt die fachlichen Funktionen |
| **Kontaktperson** | externer Ansprechpartner oder Benachrichtigungsempfänger | keine, da kein Login | keine | keines |

## 3. Abgrenzung der drei Admin-Stufen

### Super-Admin

Der Super-Admin wartet die Installation. Er darf ohne zusätzliches Rechte-Set alles sehen und ausführen.

Zusätzlich zu den Fähigkeiten eines Zentral-Admins darf er:

- installationsweite Einstellungen und Mandantenfähigkeit verwalten,
- Super-Admins und Zentral-Admins ernennen oder zurückstufen,
- technische Diagnose- und Supportfunktionen verwenden,
- Übersetzungsdateien und vergleichbare systemweite Ressourcen verwalten,
- ausdrücklich geschützte Sicherheitsausnahmen ausführen, beispielsweise verwendete Konfigurationselemente trotz Sperre löschen,
- sämtliche Organisations- und Benutzerrechte im Supportfall übergehen.

Der Super-Admin ist im späteren Betrieb üblicherweise kein fachlich verantwortlicher Anwender. Sein Vollzugriff dient Installation, Wartung und Fehlersuche.

### Zentral-Admin

Der Zentral-Admin betreibt Vectory fachlich über alle Organisationen hinweg. Bei einem mandantenfähigen System gehört er zur zentralen Organisation des Dienstleisters. Ohne Mandantenfähigkeit entspricht er der verantwortlichen Leitung des Industriekunden.

Er darf:

- alle Projekte und operativen Daten aller Organisationen sehen,
- organisationsübergreifend planen und Ressourcen beurteilen,
- Personen, fachliche Stammdaten und Konfigurationen aller Organisationen verwalten,
- Rechte-Sets und deren Zuordnung pflegen,
- Organisations-Admins ernennen und zurückstufen,
- zwischen allen Organisationen wechseln.

Er darf keine installationsweiten oder technischen Super-Admin-Funktionen verwenden und keine Super- oder Zentral-Admins ernennen.

### Organisations-Admin

Der Organisations-Admin verwaltet ausschließlich seine eigene Organisation.

Er darf:

- alle Projekte, Personen und fachlichen Daten seiner Organisation sehen,
- die fachliche Konfiguration seiner Organisation verwalten,
- innerhalb seiner Organisation ohne Rechte-Set alle normalen Vectory-Funktionen verwenden.

Er darf nicht:

- zu anderen Organisationen wechseln,
- Projekte oder vertrauliche Details anderer Organisationen sehen,
- organisationsübergreifende Konfigurationen verändern,
- Zugriffsstufen ernennen oder verändern.

Eine Kundenfreigabe oder Personenzuordnung darf diese Grenze nicht erweitern.

**Bewusste Entscheidung (Ralf, 2026-10-03):** Organisations- und Zentral-Admins erhalten automatisch alle Benutzerrechte des Katalogs, auch die personenbezogenen Planungs- und Stundenauswertungen. Ein bewusst kleines Rechte-Set schränkt einen Admin nicht ein. Begründung: Admins sind ausgewählte Personen aus der Redaktionsleitung und keine IT-Administratoren. Kunden müssen bei der Einführung entsprechend geschult werden. Sollte sich das als nicht akzeptabel erweisen, wird nachgebessert, beispielsweise durch ein eigenes Recht für personenbezogene Auswertungen.

## 4. Was Rechte-Sets leisten

Rechte-Sets sind ausschließlich für User bestimmt. Sie bündeln konkrete fachliche Fähigkeiten, beispielsweise:

- Projekte anlegen oder bearbeiten,
- Projektpersonen verwalten,
- erweiterte Planung und personenbezogene Auswertungen anzeigen,
- Kalendereinträge anderer Personen verwalten,
- Zeiterfassungsübersichten einsehen.

Verbindliche Regeln:

- Jede Person kann höchstens ein Rechte-Set besitzen; Basis-Sets und Bausteine können dessen Inhalt erweitern.
- Das Zuweisen, Wechseln oder Entfernen eines Rechte-Sets verändert niemals die Zugriffsstufe.
- Ein umfangreiches Rechte-Set kann einen User nicht zum Admin machen.
- Ein Rechte-Set kann keine fremde Organisation freischalten.
- Die Bezeichnung **Alle Benutzerrechte** meint alle konfigurierbaren Benutzerrechte, keinen Admin-Zugang.
- Benötigt eine Leitungskraft nur einzelne zusätzliche Funktionen, bleibt sie User und erhält ein passendes Rechte-Set.

## 5. Organisationsgrenzen

Die Organisationsgrenze ist eine Sicherheitsregel und nicht frei konfigurierbar:

- Super- und Zentral-Admins erreichen alle Organisationen.
- Organisations-Admins erreichen nur ihre eigene Organisation.
- User erreichen ihre eigene Organisation und die fachlich ausdrücklich freigegebenen Bereiche.
- Kontaktpersonen haben keinen Systemzugriff.

Die aktive Organisation bestimmt den aktuellen Arbeitskontext. Sie darf die grundsätzliche Reichweite der Zugriffsstufe nicht vergrößern.

## 6. Ernennung und Änderung

| Aktion | Darf ausgeführt werden von |
|---|---|
| User zum Organisations-Admin ernennen | Zentral-Admin oder Super-Admin |
| Organisations-Admin zum User zurückstufen | Zentral-Admin oder Super-Admin |
| User zum Zentral-Admin ernennen | nur Super-Admin; Person muss zur zentralen Organisation gehören |
| Zentral-Admin zurückstufen | nur Super-Admin |
| Super-Admin ernennen oder zurückstufen | nur Super-Admin |

Der letzte verbleibende Super-Admin darf nicht zurückgestuft werden.

## 7. Prüfregel für neue Funktionen

Bei jeder neuen Seite, Aktion oder API müssen diese Fragen in dieser Reihenfolge beantwortet werden:

1. **Zugriffsstufe:** Ist die Funktion technisch, administrativ oder regulär fachlich?
2. **Organisationsbereich:** Auf welche Organisationen und Datensätze darf der Nutzer zugreifen?
3. **Benutzerrecht:** Benötigt ein User dafür ein neues oder vorhandenes Recht?
4. **Datensatzbezug:** Gelten zusätzliche Regeln wie Eigentümer, Projektbeteiligung oder Sichtbarkeitsattribute?
5. **Oberfläche:** Werden nicht erlaubte Aktionen verborgen und serverseitig trotzdem zuverlässig gesperrt?
6. **Tests:** Sind Super-, Zentral- und Organisations-Admin sowie ein User mit und ohne Recht abgedeckt?

Direkte, über einzelne Controller und Ansichten verstreute Rollenvergleiche sind zu vermeiden. Zugriffsstufen werden zentral über `App\Support\AccessLevel` beziehungsweise die Methoden am `User` geprüft; Organisationszugriffe über `App\Support\CurrentTenant`.

## 8. Beispiel Projektplanung

- Super- und Zentral-Admin sehen die echten Projekte und Auslastungen aller für die Planung relevanten Organisationen.
- Ein Organisations-Admin sieht innerhalb seiner Organisation die zulässigen Personen und Projektdaten, aber keine vertraulichen Projektdetails anderer Organisationen.
- Ein User mit Login und mindestens einer Funktionsgruppe kann die Planungsseite öffnen. Ohne `planning.view` sieht er dort ausschließlich den Tab **Projektplanung**, sich selbst im Personenfilter und nur Projekte, denen er als Projektperson zugeordnet ist. Der Organisationsfilter bleibt auf seine freigeschalteten Organisationen begrenzt.
- `planning.view` schaltet die Tabs **Stunden**, **Grundlastbasis**, **Grundlast/Person** und **Arbeitszeit** sowie die vollständige Personenansicht innerhalb der zulässigen Organisationssicht frei.
- Im Planungstab eines Projekts sehen User ohne `planning.view` nur die Planstunden je Funktionsgruppe. Das Lösen einer Schablonenverknüpfung, die Bearbeitung der Funktionsgruppenstunden und die Verteilung auf Projektpersonen erfordern `planning.view`.
- Im Zeiten-Tab eines Projekts sind die aggregierten Ansichten **Projektstunden** und **Zeitverlauf** für alle Projektberechtigten sichtbar. **Personen & Tage** sowie personenbezogene Modi und Aufschlüsselungen im Zeitverlauf erfordern `planning.view`.
- **Kritische Projekte** ist für Standard-User auf die Projekte begrenzt, denen ihre Person als Projektbeteiligter zugeordnet ist. `critical_projects.view_all` erweitert die Sicht auf alle Projekte der freigegebenen Organisationen. Organisations-Admins sehen alle Projekte ihrer Organisation; Zentral- und Super-Admins sehen alle Projekte aller Organisationen der Installation.
- Die Attribute **Ressourcenplanung** und **Kalender** bestimmen nur, ob eine Person fachlich in diesen Modulen teilnimmt.

## 9. Begriffe

- Heimat-Admin → **Zentral-Admin**
- Kunden-Admin oder Kundekunden-Admin → **Organisations-Admin**
- Systemrolle → **Zugriffsstufe**
- hartcodiert → bevorzugt **systemseitig festgelegt** oder **verbindliche Systemregel**

Die fachliche „Rolle“ in den Personendetails bleibt ein beschreibendes Personenmerkmal und ist unabhängig von allen Zugriffsstufen und Rechten.

## 10. Technische Referenz

Die Werte in `users.role` lauten:

| Fachlicher Name | Technischer Wert |
|---|---|
| Super-Admin | `super_admin` |
| Zentral-Admin | `central_admin` |
| Organisations-Admin | `organization_admin` |
| User | `user` |

Zentrale Stellen:

- `App\Support\AccessLevel`: Namen und Eigenschaften der Zugriffsstufen.
- `App\Support\CurrentTenant`: zulässige Organisationen und Organisationswechsel.
- `App\Providers\AppServiceProvider`: zentrale Gate-Auswertung von Admin-Stufen und Benutzerrechten.
- `Person::hasPermission()` und `PermissionTemplate`: fachliche Rechte eines Users.

Super-Admins dürfen jedes Gate übergehen. Zentral- und Organisations-Admins dürfen alle im Rechtekatalog geführten fachlichen Aktionen ausführen; besondere technische Gates bleiben dem Super-Admin vorbehalten. User erhalten nur die Rechte ihres Rechte-Sets.
