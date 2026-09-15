<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Hilfeartikel des Hilfesystems (siehe Migration) - bewusst kein
 * BelongsToTenant, gilt für alle Mandanten gleich.
 */
#[Fillable(['key', 'route_names', 'parent_id', 'position'])]
class HelpArticle extends Model
{
    /**
     * "3-Ebenen-Hilfestruktur" (Ralf, 2026-09-15): mehr Verschachtelung
     * würde die Navigation links im Hilfe-Panel unübersichtlich machen -
     * wird in der Verwaltung beim Einrücken durchgesetzt (siehe
     * Admin\HelpArticleController::indent()).
     */
    public const MAX_DEPTH = 3;
    /**
     * Sprachen, für die die Verwaltung Reiter anbietet - "de" ist die
     * Quellsprache (Ralf schreibt sie selbst), weitere folgen bei Bedarf,
     * ohne Strukturänderung (siehe Migration).
     *
     * @var array<string, string>
     */
    public const AVAILABLE_LOCALES = [
        'de' => 'Deutsch',
        'en' => 'English',
    ];

    /**
     * Quellsprache - Fallback, wenn eine andere Sprache für einen Artikel
     * (noch) keine Übersetzung hat.
     */
    public const PRIMARY_LOCALE = 'de';

    protected function casts(): array
    {
        return [
            'route_names' => 'array',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HelpArticleTranslation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    /**
     * Ebene im Baum, Ebene 1 = kein Elternteil. Läuft die parent_id-Kette
     * hoch statt rekursiv zu laden - bei max. 3 Ebenen minimaler Aufwand,
     * auch ohne dass $allArticles vorab geladen wurde.
     */
    public function depth(?Collection $allArticles = null): int
    {
        $depth = 1;
        $current = $this;

        while ($current->parent_id !== null) {
            $current = $allArticles?->firstWhere('id', $current->parent_id) ?? $current->parent()->first();
            if (! $current) {
                break;
            }
            $depth++;
        }

        return $depth;
    }

    /**
     * Alle Artikel (inkl. Übersetzungen) als verschachtelter Baum,
     * gruppiert nach parent_id/position - eine Abfrage statt rekursiver
     * Eager-Loads, bei der zu erwartenden Artikelmenge unproblematisch.
     * Jeder Knoten bekommt zusätzlich 'depth' (1-3) für Einrück-Darstellung
     * und Einrücken-Button-Sperre in der Verwaltung.
     *
     * @return Collection<int, self>
     */
    public static function tree(): Collection
    {
        $all = self::query()->with('translations')->orderBy('position')->get();
        $byParent = $all->groupBy('parent_id');

        $attach = function (Collection $nodes, int $depth) use (&$attach, $byParent) {
            return $nodes->map(function (self $node) use (&$attach, $byParent, $depth) {
                $node->setAttribute('depth', $depth);
                $node->setRelation('children', $attach($byParent->get($node->id, collect()), $depth + 1));

                return $node;
            });
        };

        return $attach($byParent->get(null, collect()), 1);
    }

    /**
     * Flache Liste aus tree() - fürs schnelle Nachschlagen per ID (rechte
     * Bearbeitungsseite) ohne zweite Abfrage.
     *
     * @return Collection<int, self>
     */
    public static function flattenTree(Collection $tree): Collection
    {
        return $tree->flatMap(fn (self $node) => collect([$node])->concat(self::flattenTree($node->children)));
    }

    /**
     * Übersetzung für die gewünschte Sprache, sonst die der Quellsprache
     * (Ralf hat evtl. noch nicht jeden Artikel übersetzt), sonst irgendeine
     * vorhandene - ein Artikel ohne jede Übersetzung sollte praktisch nicht
     * vorkommen (wird beim Anlegen direkt mit einer Übersetzung erzeugt).
     */
    public function translation(string $locale): ?HelpArticleTranslation
    {
        $byLocale = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();

        return $byLocale->firstWhere('locale', $locale)
            ?? $byLocale->firstWhere('locale', self::PRIMARY_LOCALE)
            ?? $byLocale->first();
    }

    /**
     * Artikel, deren aktuelle Route in route_names steht - normalerweise
     * genau einer, theoretisch mehrere möglich (z.B. zwei Artikel für
     * dieselbe Seite aus unterschiedlichen Blickwinkeln).
     */
    public function scopeForRoute(Builder $query, string $routeName): Builder
    {
        return $query->whereJsonContains('route_names', $routeName);
    }

    /**
     * Einfacher Stichwort-Treffer über Titel/Keywords/Text der gewünschten
     * Sprache - reicht für die zu erwartende Artikel-Anzahl, keine
     * Volltextsuche nötig.
     *
     * @return Collection<int, self>
     */
    public static function search(string $term, string $locale): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        return self::query()
            ->whereHas('translations', function (Builder $query) use ($term, $locale) {
                $query->where('locale', $locale)
                    ->where(function (Builder $query) use ($term) {
                        $query->where('title', 'like', "%{$term}%")
                            ->orWhere('keywords', 'like', "%{$term}%")
                            ->orWhere('body', 'like', "%{$term}%");
                    });
            })
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->get()
            ->sortBy(function (self $article) use ($term, $locale) {
                $t = $article->translation($locale);
                $title = mb_strtolower($t?->title ?? '');
                $needle = mb_strtolower($term);

                // Titeltreffer zuerst, dann Keyword-Treffer, dann reine
                // Volltext-Treffer - grobe, aber ausreichende Relevanz ohne
                // eigene Scoring-Infrastruktur.
                if (str_contains($title, $needle)) {
                    return 0;
                }
                if (str_contains(mb_strtolower($t?->keywords ?? ''), $needle)) {
                    return 1;
                }

                return 2;
            })
            ->values();
    }
}
