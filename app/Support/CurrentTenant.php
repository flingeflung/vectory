<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Frischer Login (Session noch leer, z.B. nach dem Einloggen) -
        // zuletzt aktiven Kunden aus der DB wiederherstellen, statt immer
        // beim Heimat-Mandanten zu starten (Ralfs Bug-Report: startet nach
        // dem Einloggen immer mit Sanitär statt dem zuletzt genutzten
        // Kunden - die Session allein überlebt einen Login nicht).
        if ($user->last_active_tenant_id && self::userCanAccess($user, $user->last_active_tenant_id)) {
            session([self::SESSION_KEY => $user->last_active_tenant_id]);

            return $user->last_active_tenant_id;
        }

        return $user->tenant_id;
    }

    /**
     * Bewusst eine rohe DB-Abfrage statt der Person::accessibleTenants()-
     * Eloquent-Relation: die würde über Persons BelongsToTenant-Scope
     * laufen, der wiederum CurrentTenant::id() aufruft - Endlosschleife,
     * während wir hier gerade erst herausfinden wollen, welcher Mandant
     * überhaupt aktiv ist.
     */
    public static function userCanAccess(User $user, int $tenantId): bool
    {
        if ($user->tenant_id === $tenantId || in_array($user->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        if (! $user->person_id) {
            return false;
        }

        return DB::table('person_tenant')
            ->where('person_id', $user->person_id)
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    public static function switchTo(int $tenantId): void
    {
        $user = Auth::user();

        abort_unless($user && self::userCanAccess($user, $tenantId), 403);

        session([self::SESSION_KEY => $tenantId]);
        $user->update(['last_active_tenant_id' => $tenantId]);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function current(): ?Tenant
    {
        $id = self::id();

        return $id ? Tenant::find($id) : null;
    }

    /**
     * Für den Umschalter in der Kopfzeile: eigener Heimat-Mandant + alle
     * zusätzlich gewährten Kunden (siehe person_tenant). Admin und
     * Super-Admin sehen automatisch ALLE Mandanten - braucht keine
     * einzelnen Freigaben (Ralf: "als Admin brauche ich Zugriff auf alle
     * Kunden meiner DL-Firma"). Leer, wenn Mandantenfähigkeit aus ist - der
     * Umschalter bleibt dann komplett unsichtbar.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    public static function availableTenants(): \Illuminate\Support\Collection
    {
        $user = Auth::user();

        if (! $user || ! SystemSetting::multiTenantEnabled()) {
            return collect();
        }

        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return Tenant::query()->orderBy('name')->get();
        }

        $extraIds = $user->person_id
            ? DB::table('person_tenant')->where('person_id', $user->person_id)->pluck('tenant_id')
            : collect();

        $ids = collect([$user->tenant_id])->merge($extraIds)->unique();

        return Tenant::query()->whereIn('id', $ids)->orderBy('name')->get();
    }
}
