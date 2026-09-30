<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserPreference;

/**
 * Katalog aller verfügbaren Dashboard-Kacheln. Neue Kachel? Hier einen
 * Eintrag ergänzen (key + label + Blade-Partial unter
 * projekte.partials.dashboard-tiles.{key} anlegen) - kein weiterer Code
 * nötig, die Kachel taucht automatisch in der Auswahl auf.
 *
 * "Meldungen" ist bewusst NICHT Teil dieses Katalogs: sie ist fix oben
 * links, nicht abwählbar, wird separat in dashboard.blade.php gerendert.
 *
 * Aktuell für alle Benutzer gleich (kein Rechtesystem) - das kommt laut
 * Absprache später noch dazu, dann greift hier eine Sichtbarkeits-Prüfung
 * pro Kachel.
 */
class DashboardTileCatalog
{
    /**
     * @var list<string>
     */
    public const DEFAULT_ACTIVE = ['recent_projects'];

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function available(): array
    {
        return [
            ['key' => 'recent_projects', 'label' => __('Zuletzt geöffnete Projekte')],
            ['key' => 'favorites', 'label' => __('Favoriten')],
            ['key' => 'tasks', 'label' => __('Aufgaben')],
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeFor(User $user): array
    {
        $layout = UserPreference::configFor($user->id, UserPreference::DASHBOARD);
        $available = array_column(self::available(), 'key');
        $active = $layout['active_tiles'] ?? self::DEFAULT_ACTIVE;

        // Nur noch existierende Kachel-Keys durchlassen (falls eine Kachel
        // später aus dem Katalog entfernt wird, verschwindet sie damit auch
        // aus alten gespeicherten Layouts statt einen Fehler zu werfen).
        return array_values(array_intersect($active, $available));
    }

    /**
     * @param  list<string>  $tileKeys
     */
    public static function persistActiveFor(User $user, array $tileKeys): void
    {
        $available = array_column(self::available(), 'key');
        $tileKeys = array_values(array_intersect(array_unique($tileKeys), $available));

        UserPreference::persist($user->id, UserPreference::DASHBOARD, ['active_tiles' => $tileKeys]);
    }
}
