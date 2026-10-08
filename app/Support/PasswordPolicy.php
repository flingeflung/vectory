<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Passwort-Regeln an einer Stelle (Ralf, 2026-10-08): Für den Betrieb im Internet gilt der hohe Standard (mindestens 12 Zeichen mit Groß-
 * und Kleinbuchstaben, Zahl und Sonderzeichen, nicht in bekannten Datenlecks). Auf dem eigenen Rechner kann AUTH_PASSWORD_POLICY=relaxed
 * (mindestens 4 Zeichen, wie bisher "nicht bevormunden") gesetzt werden. Gilt für Aktivierung, Passwort ändern und Zurücksetzen.
 */
final class PasswordPolicy
{
    public static function strict(): bool
    {
        return config('auth.password_policy', 'strict') !== 'relaxed';
    }

    /** @return list<mixed> */
    public static function rules(bool $confirmed = true): array
    {
        $rules = ['required', 'string'];
        if ($confirmed) {
            $rules[] = 'confirmed';
        }
        if (! self::strict()) {
            $rules[] = 'min:4';

            return $rules;
        }

        $password = Password::min(12)->mixedCase()->numbers()->symbols();
        if (config('auth.password_leak_check', true)) {
            $password = $password->uncompromised();
        }
        $rules[] = $password;

        return $rules;
    }

    /** Verständliche Meldungen in Sie-Form für alle Passwort-Regeln. @return array<string, string> */
    public static function messages(string $field = 'password'): array
    {
        return [
            "$field.required" => __('Bitte wählen Sie ein Passwort.'),
            "$field.min" => self::strict() ? __('Das Passwort muss mindestens 12 Zeichen lang sein.') : __('Das Passwort muss mindestens 4 Zeichen lang sein.'),
            "$field.mixed" => __('Das Passwort muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.'),
            "$field.letters" => __('Das Passwort muss mindestens einen Buchstaben enthalten.'),
            "$field.numbers" => __('Das Passwort muss mindestens eine Zahl enthalten.'),
            "$field.symbols" => __('Das Passwort muss mindestens ein Sonderzeichen enthalten.'),
            "$field.uncompromised" => __('Das Passwort wurde in einem bekannten Datenleck gefunden. Bitte wählen Sie ein anderes Passwort.'),
            "$field.confirmed" => __('Die Passwortbestätigung stimmt nicht überein.'),
        ];
    }

    /** Kurzer Hinweis für Formulare. */
    public static function hint(): string
    {
        return self::strict()
            ? __('Mindestens 12 Zeichen mit Groß- und Kleinbuchstaben, einer Zahl und einem Sonderzeichen.')
            : __('Mindestens 4 Zeichen.');
    }
}
