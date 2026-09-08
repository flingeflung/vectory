<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Installations-weite Einstellungen (kein tenant_id, siehe Migration) -
 * bekannte Keys analog zu Setting::DEFINITIONS, aber ohne Mandantenbezug.
 * Nur über Admin > Superadmin (Super-Admin-only) änderbar.
 */
#[Fillable(['key', 'value'])]
class SystemSetting extends Model
{
    public const MULTI_TENANT_ENABLED = 'multi_tenant_enabled';

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function multiTenantEnabled(): bool
    {
        return static::get(self::MULTI_TENANT_ENABLED, '0') === '1';
    }

    /**
     * "Firma" (die Person-Markierung, Company-Modell) heißt je nach Kontext
     * anders - bei Mandantenfähigkeit sind das Subunternehmer des DL, ohne
     * Mandantenfähigkeit die Dienstleisterfirmen, für die eine Person
     * arbeitet (Ralf: "Firma und Kunde beißt sich ein wenig").
     */
    public static function companyLabel(): string
    {
        return self::multiTenantEnabled() ? 'Subunternehmer' : 'Dienstleisterfirma';
    }

    public static function companyLabelPlural(): string
    {
        return self::multiTenantEnabled() ? 'Subunternehmer' : 'Dienstleisterfirmen';
    }
}
