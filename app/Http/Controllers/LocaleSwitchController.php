<?php

namespace App\Http\Controllers;

use App\Support\AvailableLocales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sprach-Umschalter (Kopfzeile) - rein sitzungsbasiert, keine dauerhafte
 * Pro-Person-Einstellung (noch kein Bedarf dafür, siehe Backlog). Gleiches
 * Muster wie TenantSwitchController.
 */
class LocaleSwitchController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = (string) $request->string('locale');
        abort_unless(AvailableLocales::isValid($locale), 422);

        session(['locale' => $locale]);

        return back();
    }
}
