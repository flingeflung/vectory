<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserPreference;

/**
 * Tab-Definition für den Hauptnavigationspunkt "Planung" (Ralf,
 * 2026-09-28) - gleiches Tabs-oben/Inhalt-darunter-Design wie der
 * Admin-Bereich (siehe admin-layout.blade.php/AdminNav), hier aber nur EINE
 * flache Tab-Ebene statt Themen-Gruppen, da "Planung" selbst schon ein
 * einzelner Hauptnavigationspunkt ist. Bewusst als Liste angelegt, damit weitere Tabs später einfach ergänzt
 * werden können.
 */
class PlanningNav
{
    private const DEFAULT_ROUTE = 'planung.projektplanung';

    public static function tabs(?User $user = null): array
    {
        $tabs = [
            ['route' => 'planung.projektplanung', 'match' => 'planung.projektplanung', 'label' => __('Projektplanung')],
            ['route' => 'planung.stunden', 'match' => 'planung.stunden', 'label' => __('Stunden')],
            ['route' => 'planung.grundlast', 'match' => 'planung.grundlast', 'label' => __('Grundlastbasis')],
            ['route' => 'planung.grundlast-person', 'match' => 'planung.grundlast-person', 'label' => __('Grundlast/Person')],
            ['route' => 'planung.arbeitszeit', 'match' => 'planung.arbeitszeit', 'label' => __('Arbeitszeit')],
            ['route' => 'planung.erinnerungen', 'match' => 'planung.erinnerungen', 'label' => __('Erinnerungen')],
        ];

        return $user !== null && ! PlanningAccess::canViewExtended($user)
            ? array_slice($tabs, 0, 1)
            : $tabs;
    }

    public static function remember(User $user, string $route): void
    {
        if (! in_array($route, self::routes($user), true)) {
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

        return in_array($route, self::routes($user), true) ? $route : self::DEFAULT_ROUTE;
    }

    /**
     * @return list<string>
     */
    private static function routes(?User $user = null): array
    {
        return array_column(self::tabs($user), 'route');
    }
}
