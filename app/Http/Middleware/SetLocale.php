<?php

namespace App\Http\Middleware;

use App\Support\AvailableLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wendet die in der Sitzung gewählte Sprache an (siehe LocaleSwitchController
 * und der Umschalter in der Kopfzeile). Kein Fallback auf Browser-/Accept-
 * Language-Erkennung - bewusst nur die explizite Nutzerauswahl, damit sich
 * niemand ungewollt in einer anderen Sprache wiederfindet.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if ($locale && AvailableLocales::isValid($locale)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
