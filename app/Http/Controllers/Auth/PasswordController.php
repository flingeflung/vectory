<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // Ralf, 2026-09-13: "min 4 reicht überall, das müssen die Leute
        // selbst wissen, wie stark sie ihr PW machen, ich möchte da nicht
        // bevormunden." - bewusst kein Laravel-Standard Password::defaults()
        // (min. 8 + Optionen), einheitlich mit dem Admin-Reset für andere
        // Personen (PersonController::resetPassword, ebenfalls min:4).
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ]);

        if ($validator->fails()) {
            // Ralf-Bug-Report, 2026-09-13: bei einem Validierungsfehler
            // sprang die Profilseite auf ihren Anfang (Standard-Browser-
            // Verhalten bei back()->withErrors() ohne Anker) - die rote
            // Fehlermeldung im weiter unten liegenden Passwort-Abschnitt
            // war dadurch nicht sichtbar, Ralf dachte, das Speichern habe
            // geklappt. Redirect jetzt mit #update-password-Anker, Browser
            // scrollt automatisch dorthin.
            return redirect(route('profile.edit').'#update-password')
                ->withErrors($validator, 'updatePassword')
                ->withInput();
        }

        $request->user()->update([
            'password' => Hash::make($validator->validated()['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
