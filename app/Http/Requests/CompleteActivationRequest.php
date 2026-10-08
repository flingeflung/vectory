<?php

namespace App\Http\Requests;

use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Benutzername und Passwort beim Aktivieren des Kontos (Ralf, 2026-10-08). */
class CompleteActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:4', 'max:50', 'regex:/\A[A-Za-z0-9._-]+\z/', Rule::unique('users', 'username')],
            'password' => PasswordPolicy::rules(),
        ];
    }

    public function messages(): array
    {
        return PasswordPolicy::messages() + [
            'username.required' => __('Bitte wählen Sie einen Benutzernamen.'),
            'username.min' => __('Der Benutzername muss mindestens 4 Zeichen lang sein.'),
            'username.max' => __('Der Benutzername darf höchstens 50 Zeichen lang sein.'),
            'username.regex' => __('Der Benutzername darf nur Buchstaben, Zahlen, Punkt, Bindestrich und Unterstrich enthalten.'),
            'username.unique' => __('Dieser Benutzername ist bereits vergeben.'),
        ];
    }
}
