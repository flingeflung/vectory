<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Meldet Nutzer ab, deren Organisation deaktiviert wurde (Ralf, 2026-10-03: Vertrag gekündigt o. Ä.) -
 * auch mitten in einer laufenden Sitzung, bei der nächsten Aktion. Die Anmeldung selbst sperrt
 * LoginRequest::authenticate().
 */
class EnsureOrganizationIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array((int) $user->tenant_id, Tenant::inactiveIds(), true)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Hinweis über die Adresse statt als Einmal-Meldung der Sitzung: Bei einer Aktion im Hintergrund
            // (fetch) lädt der Browser die Anmeldeseite erst unsichtbar und verbraucht dabei die Meldung -
            // danach öffnet sich die Seite ohne Text (Ralf, 2026-10-03). Die Adresse überlebt das.
            return redirect()->route('login', ['hinweis' => 'organisation-inaktiv']);
        }

        return $next($request);
    }
}
