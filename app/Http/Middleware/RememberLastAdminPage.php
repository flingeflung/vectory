<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Merkt sich die zuletzt besuchte Admin-Unterseite (Personen, Rechte, ...),
 * damit der "Admin"-Link in der Seitenleiste dorthin zurückspringt statt
 * immer bei Personen zu starten (Ralf-Bug-Report). Nur echte
 * Tab-Seitenaufrufe zählen - AJAX-Nachladen innerhalb eines Tabs (z.B. die
 * Firma/Abteilung/Geschäftsbereich/Rolle-Overlays im Personen-Tab, per
 * X-Overlay-Header erkennbar) verändert die gemerkte Seite bewusst nicht.
 */
class RememberLastAdminPage
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nur echte Seitenaufrufe merken: ein per fetch() geladenes Fragment
        // (z.B. die Zugriffsmatrix der Personenverwaltung) trägt nicht immer
        // den X-Overlay-Header, würde sonst als "letzte Admin-Seite" landen
        // und beim Klick auf "Admin" roh ohne Layout angezeigt (Ralf-Bug-
        // Report 2026-09-19). Sec-Fetch-Dest setzt der Browser selbst:
        // "document" = Navigation, "empty" = fetch()/XHR.
        $isNavigation = ! $request->headers->has('Sec-Fetch-Dest') || $request->header('Sec-Fetch-Dest') === 'document';

        if ($request->isMethod('GET') && $isNavigation && $request->header('X-Overlay') !== '1') {
            session(['admin.last_tab_url' => $request->fullUrl()]);
        }

        return $next($request);
    }
}
