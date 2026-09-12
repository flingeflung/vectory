<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    /**
     * Abwesenheits-Markierung (Ralf, 2026-09-12) - im Unterschied zum
     * Checkbox-Feld oben mit echtem Speichern-Button statt Sofort-Submit
     * (CLAUDE.md-Konvention: hat eine validierte Dateneingabe, keine reine
     * Anzeige-/Filtersteuerung mehr). Liegt "bis wann" in der Vergangenheit,
     * kommt eine Fehlermeldung statt stillschweigend zu speichern.
     */
    public function updateAbsence(Request $request): RedirectResponse
    {
        $person = $request->user()->person;
        abort_unless($person, 404);

        $validator = Validator::make($request->all(), [
            'is_absent' => ['boolean'],
            'absent_until' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            // Laravels Standardtext übersetzt "today" nicht mit ("... nach
            // dem oder gleich dem today sein") - eigene, klare Meldung
            // statt der generischen Übersetzung (Ralf: "Prüfung, ob das
            // Datum nach vorne liegt, sonst Warnmeldung").
            'absent_until.after_or_equal' => __('Das Datum darf nicht in der Vergangenheit liegen.'),
        ], ['absent_until' => __('Abwesend bis')]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'absence')->withInput();
        }

        $validated = $validator->validated();

        $person->update([
            'is_absent' => $request->boolean('is_absent'),
            'absent_until' => $validated['absent_until'] ?? null,
        ]);

        return redirect()->route('settings')->with('status', 'absence-updated');
    }
}
