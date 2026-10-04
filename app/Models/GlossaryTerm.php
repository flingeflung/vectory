<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Begriffsverzeichnis (Ralf, 2026-10-04): ein Begriff (z. B. "Grundlast") führt zur Seite, auf der man ihn ändert.
 * Die Zuordnung steht nur hier; in Hilfetexten als ((Begriff)) bzw. ((Begriff|Anzeigetext)), in Oberflächentexten
 * als <x-term>Begriff</x-term>. Der Link öffnet in einem neuen Browser-Tab. Fehlt das Recht, bleibt der Begriff
 * Text mit Erklärung als Tooltip.
 *
 * Bewusst ohne tenant_id: gilt installationsweit, gepflegt vom Super-Admin.
 */
class GlossaryTerm extends Model
{
    protected $fillable = ['term', 'route_name', 'ability', 'description'];

    /** @var array<string, self>|null */
    private static ?array $cache = null;

    public static function forgetCache(): void
    {
        self::$cache = null;
    }

    public static function lookup(string $term): ?self
    {
        self::$cache ??= self::query()->get()->keyBy(fn (self $row) => mb_strtolower($row->term))->all();

        return self::$cache[mb_strtolower(trim($term))] ?? null;
    }

    /** Ziel-URL oder null, wenn die Seite fehlt oder (Parameter nötig) nicht auflösbar ist. */
    public function url(): ?string
    {
        if (! Route::has($this->route_name)) {
            return null;
        }
        try {
            return route($this->route_name);
        } catch (Throwable) {
            return null;
        }
    }

    /** HTML für einen Begriff im Text: Link (neuer Tab) mit Tooltip, ohne Recht nur Tooltip, unbekannt = Text. */
    public static function link(string $term, ?string $label = null): string
    {
        $label = $label !== null && trim($label) !== '' ? trim($label) : trim($term);
        $entry = self::lookup($term);
        if (! $entry) {
            return e($label);
        }

        $title = e((string) $entry->description);
        $url = $entry->url();
        $user = auth()->user();
        $allowed = $url !== null && $user && ($entry->ability === null || $entry->ability === '' || $user->can($entry->ability));

        if (! $allowed) {
            return '<span class="underline decoration-dotted decoration-gray-400" title="'.$title.'">'.e($label).'</span>';
        }

        return '<a href="'.e($url).'" target="_blank" rel="noopener" class="underline decoration-dotted" title="'.$title.'">'.e($label).'</a>';
    }
}
