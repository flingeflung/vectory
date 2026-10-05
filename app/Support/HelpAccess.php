<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Facades\Route;

/**
 * Beschreibung, wer die Hauptseite sehen kann (nur zur Anzeige für den Super-Admin im Hilfe-Panel). Quelle: config/help-access.php,
 * sonst die can:-Regeln der Route (Admin-Seiten).
 */
final class HelpAccess
{
    /**
     * @param  list<string>  $keys  Schlüssel vom genauesten zum gröbsten (Reiter, Dialog), danach der Routenname
     */
    public static function describe(array $keys, string $routeName = ''): ?string
    {
        $config = (array) config('help-access', []);
        foreach ([...$keys, $routeName] as $key) {
            if ($key !== '' && isset($config[$key])) {
                return (string) $config[$key];
            }
        }

        return $routeName !== '' ? self::fromRoute($routeName) : null;
    }

    private static function fromRoute(string $routeName): ?string
    {
        $route = Route::getRoutes()->getByName($routeName);
        if (! $route) {
            return null;
        }

        $levels = [
            'access-superadmin' => __('Nur Super-Admin'),
            'access-central-admin' => __('Zentral-Admin und Super-Admin'),
            'access-admin' => __('Administratoren (Organisations-, Zentral- und Super-Admin)'),
        ];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                $ability = substr($middleware, 4);

                return $levels[$ability] ?? __('Mit dem Recht „:right“', ['right' => Permission::query()->where('key', $ability)->value('label') ?? $ability]);
            }
        }

        return null;
    }
}
