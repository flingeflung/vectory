<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Einmal nutzbarer Aktivierungslink eines vorbereiteten Kontos; gespeichert wird nur der SHA-256-Hash des Tokens. */
#[Fillable(['user_id', 'token_hash', 'expires_at'])]
class AccountActivationToken extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
