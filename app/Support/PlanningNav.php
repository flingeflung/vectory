<?php

namespace App\Support;

/**
 * Reiter-Definition für den neuen Hauptnavigationspunkt "Planung" (Ralf,
 * 2026-09-28) - gleiches Reiter-oben/Inhalt-darunter-Design wie der
 * Admin-Bereich (siehe admin-layout.blade.php/AdminNav), hier aber nur EINE
 * flache Reiter-Ebene statt Themen-Gruppen, da "Planung" selbst schon ein
 * einzelner Hauptnavigationspunkt ist. "Im Moment nur einen [Reiter]" (Ralf) -
 * bewusst als Liste angelegt, damit weitere Reiter später einfach ergänzt
 * werden können.
 */
class PlanningNav
{
    public static function tabs(): array
    {
        return [
            ['route' => 'planung.stunden', 'match' => 'planung.stunden', 'label' => __('Stunden')],
            ['route' => 'planung.grundlast', 'match' => 'planung.grundlast', 'label' => __('Grundlast')],
            ['route' => 'planung.arbeitszeit', 'match' => 'planung.arbeitszeit', 'label' => __('Arbeitszeit')],
        ];
    }
}
