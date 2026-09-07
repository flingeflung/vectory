<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Zentrale (einzige) Stelle im Code, die "welcher Mandant ist gerade aktiv"
 * beantwortet - ersetzt die früher direkten Auth::user()->tenant_id-
 * Zugriffe im BelongsToTenant-Trait. Löst Ralfs Anforderung "es muss an
 * zentraler Stelle passieren" auch codeseitig, nicht nur in der Oberfläche.
 *
 * Ohne Mandantenfähigkeit (SystemSetting::multiTenantEnabled() aus) bleibt
 * das Verhalten wie vorher: der eigene Heimat-Mandant des Nutzers - mit
 * einer Ausnahme, die NUR für unsere eigene Testinstallation relevant ist
 * (Ralf: "wird in der Praxis natürlich nicht verwendet"): existieren aus
 * einem früheren Test mehrere Mandanten, obwohl MF gerade aus ist, wird
 * IMMER der zuerst angelegte Mandant verwendet, unabhängig vom (dann
 * ohnehin irrelevanten) Sitzungs-Umschalter. Bei einer echten Modell-2-
 * Installation gibt es ohnehin nur genau einen Mandanten - keine
 * Mehrdeutigkeit, kein Unterschied zum bisherigen Verhalten.
 */
class CurrentTenant
{
    private const SESSION_KEY = 'active_tenant_id';

    public static function id(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if (! SystemSetting::multiTenantEnabled()) {
            return Tenant::query()->orderBy('id')->value('id') ?? $user->tenant_id;
        }

        $sessionTenantId = session(self::SESSION_KEY);

        if ($sessionTenantId && self::userCanAccess($user, (int) $sessionTenantId)) {
            return (int) $sessionTenantId;
        }

        return $user->tenant_id;
    }

    public static function userCanAccess(User $user, int $tenantId): bool
    {
        if ($user->tenant_id === $tenantId) {
            return true;
        }

        return $user->person?->accessibleTenants()->where('tenants.id', $tenantId)->exists() ?? false;
    }

    public static function switchTo(int $tenantId): void
    {
        $user = Auth::user();

        abort_unless($user && self::userCanAccess($user, $tenantId), 403);

        session([self::SESSION_KEY => $tenantId]);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
