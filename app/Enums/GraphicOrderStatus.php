<?php

namespace App\Enums;

/**
 * Status-Katalog für Illustrationsaufträge - fest verdrahtet statt
 * Tenant-Tabelle (Vietto: grafikerstellung_status). is_open()/isDiscarded()
 * steuern echte Logik (Ampel-Farben, "offen/erledigt"-Zählung, ob ein
 * Auftrag als "Grafikauftrag vorhanden" zählt), kein freier
 * Tenant-Einstellwert - sonst bricht die App (Ralf-Entscheidung, nachdem
 * ein neuer Mandant ohne Status-Katalog beim Speichern scheiterte). Werte
 * 1-8 entsprechen exakt Viettos valID/legacy_id, damit bestehende
 * graphic_orders-Zeilen unverändert gültig bleiben.
 */
enum GraphicOrderStatus: int
{
    case NeuerAuftrag = 1;
    case InterneBearbeitung = 2;
    case ExternBeauftragt = 3;
    case VonExternZurueck = 4;
    case ZurFreigabe = 5;
    case ZurKorrektur = 6;
    case FertigUndAbgelegt = 7;
    case Verworfen = 8;

    public function label(): string
    {
        return match ($this) {
            self::NeuerAuftrag => __('Neuer Auftrag'),
            self::InterneBearbeitung => __('Interne Bearbeitung'),
            self::ExternBeauftragt => __('Extern beauftragt'),
            self::VonExternZurueck => __('Von extern zurück'),
            self::ZurFreigabe => __('Zur Freigabe'),
            self::ZurKorrektur => __('Zur Korrektur'),
            self::FertigUndAbgelegt => __('Fertig und abgelegt'),
            self::Verworfen => __('Verworfen'),
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::FertigUndAbgelegt, self::Verworfen], true);
    }

    public function isDiscarded(): bool
    {
        return $this === self::Verworfen;
    }
}
