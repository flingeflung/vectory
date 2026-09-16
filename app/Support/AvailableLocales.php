<?php

namespace App\Support;

/**
 * Zentrale Liste der im Tool wählbaren Oberflächen-Sprachen (Ralf,
 * 2026-09-16). Eine weitere Sprache hinzuzufügen braucht KEIN Datenbank-
 * Schema - nur hier einen Eintrag ergänzen, dann `php artisan lang:sync
 * <code>` laufen lassen (legt/ergänzt lang/<code>.json mit allen im Code
 * verwendeten Texten), übersetzen lassen und über Admin > Superadmin
 * wieder hochladen (siehe SyncLangJson/SuperAdminController). "de" ist die
 * Quellsprache selbst - taucht deshalb nicht in der Übersetzungsdatei-
 * Verwaltung auf, nur im Sprachumschalter.
 */
class AvailableLocales
{
    private const LOCALES = [
        'de' => 'Deutsch',
        'en' => 'English',
    ];

    public static function all(): array
    {
        return self::LOCALES;
    }

    /**
     * @return array<string, string> alle Sprachen außer der Quellsprache - das sind die einzigen, für die eine Übersetzungsdatei Sinn ergibt.
     */
    public static function translatable(): array
    {
        return array_diff_key(self::LOCALES, ['de' => null]);
    }

    public static function isValid(string $locale): bool
    {
        return array_key_exists($locale, self::LOCALES);
    }
}
