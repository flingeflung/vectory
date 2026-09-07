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

        CurrentTenant::switchTo($request->integer('tenant_id'));

        return back();
    }
}
