<?php

namespace App\Services;

use App\Mail\AccountActivationMail;
use App\Models\AccountActivationToken;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Erzeugt für ein vorbereitetes Konto einen zufälligen Token, speichert nur dessen SHA-256-Hash mit Ablaufzeit (ein Link je Konto, ein neuer
 * ersetzt den alten) und verschickt die Mail mit dem Link (Ralf, 2026-10-08, Vorbild ebiabi85).
 */
class ActivationLinkSender
{
    public function send(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $hours = (int) config('auth.activation_lifetime_hours', 72);

        AccountActivationToken::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours($hours)],
        );

        Mail::to($user->email)->send(new AccountActivationMail((string) ($user->person?->first_name ?: $user->name), $token, $hours));
    }

    /** Darf für dieses Konto ein Aktivierungslink erstellt werden? */
    public static function eligible(?User $user): bool
    {
        return $user !== null
            && $user->person !== null
            && (bool) $user->person->active
            && $user->isPending()
            && $user->username === null
            && $user->password === null
            && $user->activated_at === null
            && filter_var($user->email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
