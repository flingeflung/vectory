<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Hilfeartikel des Hilfesystems (siehe Migration) - bewusst kein
 * BelongsToTenant, gilt für alle Mandanten gleich.
 */
#[Fillable(['key', 'route_names'])]
class HelpArticle extends Model
{
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
