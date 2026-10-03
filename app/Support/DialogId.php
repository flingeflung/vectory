<?php

namespace App\Support;

use App\Models\HelpArticle;

/**
 * Kurze, feste Kennung jedes Dialogs/Overlays (Ralf, 2026-10-03): steht klein unten links im
 * Dialog, damit sich Fehler ("Such die ID unten links und nenne sie mir") und Hilfeseiten ohne
 * lange Beschreibung einem Dialog zuordnen lassen.
 *
 * Die ID ist FEST: config/dialog-ids.php ordnet jedem Dialognamen (<x-modal name="…">) seine ID
 * zu. Wird ein Dialog umbenannt, trägt man den neuen Namen mit der ALTEN ID dort ein - sonst
 * verliert die Hilfeseite ihre Zuordnung (ein Test warnt davor, siehe DialogHelpTest). Neue
 * Dialoge trägt `php artisan dialogs:sync` ein. Ein angehängter Datensatz-Zähler
 * ("projektgruppen-panel-123") wird entfernt, damit alle Instanzen eines Dialogtyps dieselbe ID
 * tragen.
 */
final class DialogId
{
    /** @var array<int, string>|null */
    private static ?array $articleIds = null;

    public static function normalize(string $modalName): string
    {
        return preg_replace('/-\d+$/', '', $modalName);
    }

    /** Aus dem Namen berechnete ID - nur für neue Dialoge (dialogs:sync) und als Rückfall. */
    public static function compute(string $normalizedName): string
    {
        $code = strtoupper(substr(base_convert(sprintf('%u', crc32($normalizedName)), 10, 36), -4));

        return 'D-'.str_pad($code, 4, '0', STR_PAD_LEFT);
    }

    public static function for(string $modalName): string
    {
        $normalized = self::normalize($modalName);

        return config('dialog-ids.'.$normalized) ?? self::compute($normalized);
    }

    /**
     * Dialog-IDs, zu denen es einen für den Nutzer sichtbaren Hilfeartikel gibt (ein Abruf je
     * Anfrage) - steuert das Fragezeichen im Dialog.
     *
     * @return array<int, string>
     */
    public static function withHelpArticle(): array
    {
        if (self::$articleIds !== null) {
            return self::$articleIds;
        }

        $user = auth()->user();

        return self::$articleIds = HelpArticle::query()
            ->whereNotNull('route_names')
            ->get()
            ->filter(fn (HelpArticle $article) => $article->isVisibleTo($user))
            ->flatMap(fn (HelpArticle $article) => $article->route_names ?? [])
            ->filter(fn ($entry) => is_string($entry) && str_starts_with($entry, 'D-'))
            ->unique()
            ->values()
            ->all();
    }

    public static function forgetCache(): void
    {
        self::$articleIds = null;
    }
}
