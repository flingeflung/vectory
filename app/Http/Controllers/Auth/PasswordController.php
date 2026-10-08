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
        // Regeln zentral in PasswordPolicy: für den Betrieb im Internet streng, lokal per AUTH_PASSWORD_POLICY=relaxed lockerer
        // (Ralf, 2026-09-13: "nicht bevormunden" gilt nur noch für den eigenen Rechner; 2026-10-08: im Internet der hohe Standard).
        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'current_password'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ], \App\Support\PasswordPolicy::messages());

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
