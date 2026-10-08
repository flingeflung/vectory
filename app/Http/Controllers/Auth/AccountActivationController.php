<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteActivationRequest;
use App\Models\AccountActivationToken;
use App\Models\User;
use App\Services\ActivationLinkSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Konto aktivieren (Ralf, 2026-10-08, Vorbild ebiabi85): Die Person bekommt einen Link, wählt dort Benutzername und Passwort, der
 * Linkklick gilt als bestätigte E-Mail-Adresse. Der Link ist einmal nutzbar und läuft ab. Wer seinen Link verloren hat, fordert einen
 * neuen an; die Antwort ist immer dieselbe, damit sich keine Adressen ausprobieren lassen.
 */
class AccountActivationController extends Controller
{
    public function requestForm(): View
    {
        return view('activation.request');
    }

    public function requestLink(Request $request, ActivationLinkSender $links): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower(trim((string) $request->input('email')));
        $neutral = __('Falls zu dieser E-Mail-Adresse ein noch nicht aktiviertes Konto vorhanden ist, wurde ein Aktivierungslink versendet.');

        $ipKey = 'activation:ip:'.hash('sha256', (string) $request->ip());
        $emailKey = 'activation:email:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($emailKey, 3)) {
            return back()->with('status', $neutral);
        }
        RateLimiter::hit($ipKey, 3600);
        RateLimiter::hit($emailKey, 3600);

        $user = User::query()->with('person')->where('email', $email)->first();
        if (ActivationLinkSender::eligible($user)) {
            DB::transaction(function () use ($user, $links): void {
                $locked = User::query()->with('person')->lockForUpdate()->find($user->id);
                if (ActivationLinkSender::eligible($locked)) {
                    $links->send($locked);
                }
            });
        }

        return back()->with('status', $neutral);
    }

    public function form(string $token): View
    {
        $activation = $this->validToken($token);

        return $activation ? view('activation.complete', ['token' => $token, 'user' => $activation->user]) : view('activation.invalid');
    }

    public function complete(CompleteActivationRequest $request, string $token): RedirectResponse
    {
        $completed = DB::transaction(function () use ($request, $token): bool {
            $activation = AccountActivationToken::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $activation || $activation->expires_at->isPast()) {
                return false;
            }
            $user = User::query()->with('person')->lockForUpdate()->find($activation->user_id);
            if (! ActivationLinkSender::eligible($user)) {
                return false;
            }

            $user->forceFill([
                'username' => $request->string('username')->toString(),
                'password' => $request->string('password')->toString(),
                'status' => User::STATUS_ACTIVE,
                'activated_at' => now(),
                'email_verified_at' => now(),
            ])->save();
            $activation->delete();

            return true;
        });

        Auth::logout();

        return $completed
            ? redirect()->route('login')->with('status', __('Ihr Konto wurde aktiviert. Sie können sich jetzt mit Ihrem Benutzernamen und Passwort anmelden.'))
            : redirect()->route('activation.invalid');
    }

    public function invalid(): View
    {
        return view('activation.invalid');
    }

    private function validToken(string $token): ?AccountActivationToken
    {
        $activation = AccountActivationToken::query()->with('user.person')->where('token_hash', hash('sha256', $token))->first();

        return $activation && ! $activation->expires_at->isPast() && ActivationLinkSender::eligible($activation->user) ? $activation : null;
    }
}
