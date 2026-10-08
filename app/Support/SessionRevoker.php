<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Beendet laufende Sitzungen eines Benutzers (Datenbank-Sitzungen); Ralf, 2026-10-08. */
class SessionRevoker
{
    /** Alle Sitzungen des Benutzers beenden, optional eine (die aktuelle) behalten. */
    public static function revoke(int $userId, ?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();

        // "Angemeldet bleiben"-Anmeldungen ungültig machen
        DB::table('users')->where('id', $userId)->update(['remember_token' => Str::random(60)]);
    }
}
