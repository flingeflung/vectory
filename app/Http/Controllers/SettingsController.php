<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Persönliche Einstellungen (pro Person, nicht pro Mandant/Admin) - bewusst
 * NICHT unter /admin, weil der komplette Admin-Bereich per
 * can:access-admin gesperrt ist und ein normaler User sonst an seine
 * eigenen Einstellungen gar nicht mehr rankäme.
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index');
    }

    /**
     * Reine Anzeige-/Filtersteuerung (keine fachlichen Daten) - löst wie
     * andere Filter-Checkboxen im Tool per onchange sofort aus, kein
     * eigener Speichern-Button nötig.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->user()->update([
            'hide_discarded_projects_on_reset' => $request->boolean('hide_discarded_projects_on_reset'),
        ]);

        return redirect()->route('settings')->with('status', 'settings-updated');
    }
}
