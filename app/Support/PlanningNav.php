<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserPreference;

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
    private const DEFAULT_ROUTE = 'planung.stunden';

    public static function tabs(): array
    {
        return [
            ['route' => 'planung.stunden', 'match' => 'planung.stunden', 'label' => __('Stunden')],
            ['route' => 'planung.projektplanung', 'match' => 'planung.projektplanung', 'label' => __('Projektplanung')],
            ['route' => 'planung.grundlast', 'match' => 'planung.grundlast', 'label' => __('Grundlastbasis')],
            ['route' => 'planung.grundlast-person', 'match' => 'planung.grundlast-person', 'label' => __('Grundlast/Person')],
            ['route' => 'planung.arbeitszeit', 'match' => 'planung.arbeitszeit', 'label' => __('Arbeitszeit')],
        ];
    }

    public static function remember(User $user, string $route): void
    {
        if (! in_array($route, self::routes(), true)) {
            return;
        }

        if (self::preferredRoute($user) === $route) {
            return;
        }

        UserPreference::persist($user->id, UserPreference::PLANNING, ['tab' => $route]);
    }

    public static function preferredRoute(User $user): string
    {
        $route = UserPreference::configFor($user->id, UserPreference::PLANNING)['tab'] ?? null;

        return in_array($route, self::routes(), true) ? $route : self::DEFAULT_ROUTE;
    }

    /**
     * @return list<string>
     */
    private static function routes(): array
    {
        return array_column(self::tabs(), 'route');
    }
}
