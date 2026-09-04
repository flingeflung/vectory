<?php

namespace App\Enums;

/**
 * Kategorie eines Vorgangs-Typs (App\Enums\ActivityType) - für die farbliche
 * Unterscheidung/Filterung im Vorgänge-Tab, sobald mehr Typen dazukommen
 * (aktuell 2, geplant deutlich mehr, siehe ActivityType-Kommentar).
 */
enum ActivityCategory: string
{
    case Workflow = 'workflow';
    case Illustration = 'illustration';

    public function label(): string
    {
        return match ($this) {
            self::Workflow => __('Workflow'),
            self::Illustration => __('Illustration'),
        };
    }

    /**
     * Vollständige Tailwind-Klasse für den Farbpunkt - bewusst als
     * kompletter Literal-String (nicht "bg-{$farbe}-500" zusammengesetzt),
     * damit Tailwinds Scanner die Klasse überhaupt findet (dynamisch
     * zusammengebaute Klassennamen werden beim Build nicht erkannt).
     */
    public function dotClass(): string
    {
        return match ($this) {
            self::Workflow => 'bg-blue-500',
            self::Illustration => 'bg-purple-500',
        };
    }
}
