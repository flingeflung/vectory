<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Der zentrale Mandanten-Umschalter (Kopfzeile) - Ralf: "Dort kann jeder
 * Projektbeteiligte die Umschaltung vornehmen, es muss also an zentraler
 * Stelle passieren." Prüft über CurrentTenant::switchTo(), ob der Nutzer
 * tatsächlich Zugriff auf den Ziel-Mandanten hat (eigener Mandant oder per
 * Kundenzugriff in der Personenverwaltung gewährt).
 */
class TenantSwitchController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::multiTenantEnabled(), 403);

        $tenantId = $request->integer('tenant_id');

        // Eine Organisation, die inzwischen deaktiviert wurde (die Liste im Schalter stammt vom Laden der Seite),
        // oder die man nicht erreichen darf: freundliche Meldung statt einer nackten Fehlerseite.
        if (! $request->user() || ! CurrentTenant::userCanAccess($request->user(), $tenantId)) {
            return back()->with('notice', __('Diese Organisation steht nicht zur Verfügung. Bitte laden Sie die Seite neu.'));
        }

        CurrentTenant::switchTo($tenantId);

        return back();
    }
}
