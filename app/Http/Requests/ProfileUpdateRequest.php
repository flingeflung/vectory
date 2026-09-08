<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Wer mit einer Person verknüpft ist, hat dort schon einen
            // Namen (first_name/last_name) - das users.name-Feld würde
            // sonst unabhängig davon auseinanderlaufen können (Ralf,
            // 2026-09-09). Ohne Personenverknüpfung (z.B. Systemkonten)
            // bleibt es das einzige Namensfeld, dann weiterhin Pflicht.
            'name' => [$this->user()->person_id ? 'sometimes' : 'required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
