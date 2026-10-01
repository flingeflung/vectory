# Zugriffsstufen und Rechte

## Getrennte Ebenen

Vectory unterscheidet verbindlich zwischen:

1. **Zugriffsstufe**: technischer und administrativer Rahmen eines Logins.
2. **Organisationsbereich**: Organisationen, deren Daten grundsätzlich erreichbar sind.
3. **Rechte-Set**: konkrete fachliche Funktionen eines Standard-Users innerhalb dieses Rahmens.
4. **Fachliche Rolle**: Tätigkeit einer Person, beispielsweise Technische Redaktion oder Projektleitung; ohne Einfluss auf Zugriffsrechte.
5. **Teilnahmeattribute**: beispielsweise Ressourcenplanung und Kalender; sie steuern die Teilnahme an einem Modul, nicht die Zugriffsstufe.

## Zugriffsstufen

| Zugriffsstufe | Bereich | Rechte-Set |
|---|---|---|
| Super-Admin | gesamte Installation einschließlich technischer Funktionen und Sicherheitsausnahmen | nicht erforderlich |
| Zentral-Admin | alle operativen Daten und alle Organisationen, ohne technische Super-Admin-Funktionen | nicht erforderlich |
| Organisations-Admin | vollständige Administration innerhalb der eigenen Organisation | nicht erforderlich |
| User | eigene und ausdrücklich freigegebene Organisationsbereiche | bestimmt die konkreten Funktionen |
| Kontaktperson | kein Login | keines |

## Verbindliche Sicherheitsregeln

- Super-Admins dürfen sämtliche Organisations- und Rechteprüfungen übergehen.
- Zentral-Admins dürfen alle fachlichen Organisationen verwalten, aber keine installationsweiten Super-Admin-Funktionen ausführen.
- Organisations-Admins bleiben immer auf ihre eigene Organisation begrenzt. Kundenfreigaben erweitern diese administrative Grenze nicht.
- Rechte-Sets können keine Admin-Zugriffsstufe verleihen und keine Organisationsgrenze erweitern.
- Das Zuweisen oder Entfernen eines Rechte-Sets verändert die Zugriffsstufe eines Logins nicht.
- Eingeschränkte Leitungs- oder Projektfunktionen werden einem User über ein Rechte-Set gegeben, ohne ihn zum Admin zu machen.

## Begriffe

- Heimat-Admin → Zentral-Admin
- Kunden-Admin beziehungsweise Kundekunden-Admin → Organisations-Admin
- Systemrolle → Zugriffsstufe

Systemseitig festgelegt bezeichnet Regeln, die nicht durch ein Rechte-Set gelockert werden können.
