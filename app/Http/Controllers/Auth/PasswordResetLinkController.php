<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Die Antwort ist immer dieselbe (Ralf, 2026-10-08), damit sich keine Adressen ausprobieren lassen. Ein noch nicht aktiviertes
        // Konto bekommt keinen Link zum Zurücksetzen - sein Passwort entsteht nur über den Aktivierungslink.
        $email = $request->string('email')->toString();
        $user = \App\Models\User::query()->with('person')->where('email', $email)->first();
        if ($user && $user->mayLogIn()) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', __('Falls zu dieser E-Mail-Adresse ein Konto vorhanden ist, wurde ein Link zum Zurücksetzen des Passworts versendet.'));
    }
}
