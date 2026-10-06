<?php

namespace App\Support;

use App\Models\PermissionTemplate;
use App\Models\User;

/**
 * Morphen (Ralf, 2026-10-06, nach Viettos "Rechte morphen"): der echte Super-Admin nimmt vorübergehend die Rolle (und bei "User" das
 * Rechte-Set) einer anderen Stufe an, um Funktionen und Sichtbarkeiten zu prüfen, ohne sich umzumelden. Er bleibt technisch derselbe
 * Benutzer (gleiche Person, gleiche Protokolleinträge) - nur Rolle, Rechte und die "eigene" Organisation wechseln. Gemerkt wird das in
 * der Sitzung; Abmelden beendet es. Greift ausschließlich bei einem echten Super-Admin (Rolle in der Datenbank).
 */
final class Morph
{
    private const SESSION_KEY = 'morph';

    /** Rollen, in die gemorpht werden kann (Super-Admin = Normalzustand, also Beenden). */
    public const ROLES = [
        AccessLevel::ORGANIZATION_ADMIN,
        AccessLevel::CENTRAL_ADMIN,
        AccessLevel::USER,
    ];

    /**
     * @return array{role: string, template_id: ?int, tenant_id: int}|null
     */
    public static function state(): ?array
    {
        if (! app()->bound('request') || ! request()->hasSession()) {
            return null;
        }

        $state = session(self::SESSION_KEY);

        return is_array($state) && in_array($state['role'] ?? null, self::ROLES, true) ? $state : null;
    }

    /** Ist die Sitzung gerade gemorpht? (Nur für einen echten Super-Admin.) */
    public static function active(?User $user): bool
    {
        return $user !== null && self::isRealSuperAdmin($user) && self::state() !== null;
    }

    /** Echte Rolle in der Datenbank - unabhängig vom Morphen. */
    public static function isRealSuperAdmin(User $user): bool
    {
        return $user->getRawOriginal('role') === AccessLevel::SUPER_ADMIN;
    }

    public static function start(string $role, ?int $templateId, int $tenantId): void
    {
        session([self::SESSION_KEY => [
            'role' => $role,
            'template_id' => $role === AccessLevel::USER ? $templateId : null,
            'tenant_id' => $tenantId,
        ]]);
    }

    public static function end(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** Rechte-Set, das bei der Rolle "User" gilt. */
    public static function template(): ?PermissionTemplate
    {
        $id = self::state()['template_id'] ?? null;

        return $id ? PermissionTemplate::query()->withoutGlobalScope('tenant')->find($id) : null;
    }

    /** Anzeigetext für den Streifen oben, z. B. "User (Rechte-Set TR)". */
    public static function label(): ?string
    {
        $state = self::state();
        if ($state === null) {
            return null;
        }

        $label = match ($state['role']) {
            AccessLevel::ORGANIZATION_ADMIN => __('Organisations-Admin'),
            AccessLevel::CENTRAL_ADMIN => __('Zentral-Admin'),
            default => __('User'),
        };

        if ($state['role'] === AccessLevel::USER) {
            $template = self::template();
            $label .= $template ? ' ('.__('Rechte-Set').' '.$template->name.')' : ' ('.__('ohne Rechte-Set').')';
        }

        return $label;
    }
}
