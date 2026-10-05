<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

#[Fillable(['help_article_id', 'locale', 'title', 'keywords', 'body'])]
class HelpArticleTranslation extends Model
{
    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }

    /**
     * Bild-Platzhalter (Ralf, 2026-09-12): "[dateiname.png]" statt der
     * sperrigen echten Markdown-Bildsyntax "![](url)" - Ralf legt die Datei
     * einfach unter public/images/hilfe/ ab und schreibt den Dateinamen in
     * eckigen Klammern in den Text. Negative Lookahead "(?!\()" verhindert
     * eine Verwechslung mit einem echten Markdown-Link "[text](url)".
     * Eigene Leerzeilen drumherum, damit CommonMark daraus einen eigenen
     * Absatz macht (Ralfs Anforderung: Bild als eigener Block, Folgetext
     * darunter statt danebengesetzt) - img ist per Tailwind-Preflight
     * ohnehin block-Element, die Leerzeilen sorgen zusätzlich für die
     * Absatztrennung im Fließtext selbst.
     */
    private const IMAGE_PLACEHOLDER_PATTERN = '/\[([\w\-. ]+\.(?:png|jpe?g|gif|webp|svg))\](?!\()/i';

    /**
     * Button-/UI-Element-Zitat (Ralf, 2026-09-12): "{+Neu}" wird zu einem
     * kleinen, nicht klickbaren Abzeichen im selben Look wie die echten
     * Sekundär-Buttons im Tool (CLAUDE.md-Konvention) - z.B. "drücke {+Neu}"
     * in einer Anleitung. Geschweifte statt eckiger Klammer, weil eckige
     * schon für den Bild-Platzhalter oben vergeben ist. Läuft NACH der
     * Markdown-Konvertierung (auf dem fertigen HTML), nicht davor wie beim
     * Bild - sonst würde html_input=strip das selbst eingefügte <span>
     * gleich wieder rausstreichen.
     */
    private const BUTTON_QUOTE_PATTERN = '/\{([^{}\n]+)\}/';

    private const BUTTON_QUOTE_CLASSES = 'inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-1.5 py-0.5 text-xs font-medium text-gray-700';

    /**
     * Verweis auf eine andere Hilfeseite (Ralf, 2026-09-12: "wenn ich einen
     * Link einfügen möchte, um dorthin zu springen, was muss ich angeben?")
     * - "[[Titel]]" statt einer technischen Adresse, damit Ralf nur den ihm
     * sichtbaren Artikel-Titel braucht, nie den intern erzeugten "key"
     * (siehe HelpArticle::search()). Klick springt im selben Panel zum
     * Zielartikel (window.helpOpenArticle(), siehe help-panel.blade.php -
     * gleiches Muster wie ein Klick auf einen Suchtreffer), statt aus dem
     * Panel raus auf die rohe Fragment-Route zu navigieren.
     */
    private const ARTICLE_LINK_PATTERN = '/\[\[([^\[\]]+)\]\]/';

    /**
     * Link zu einer echten Tool-Seite (Ralf, 2026-09-12: "das ist ja dann
     * eine absolute URL, keine relative. In einer anderen Umgebung
     * funktioniert er ja nicht!") - normale Markdown-Link-Syntax
     * "[Text](route:admin.kunden)" statt eines fest eingetippten Pfads.
     * "route:" ist kein echtes URL-Schema, nur ein Marker, den wir NACH der
     * Markdown-Konvertierung selbst durch die per route()-Helper (also
     * umgebungsunabhängig) erzeugte echte Adresse ersetzen. Der Routenname
     * ist derselbe technische Wert, den das Hilfe-Panel schon anzeigt, wenn
     * für eine Seite noch keine Hilfeseite existiert (siehe
     * HelpController::results()).
     */
    private const ROUTE_LINK_PATTERN = '/<a href="route:([a-zA-Z0-9_.\-]+)">(.*?)<\/a>/';

    /**
     * Markdown -> HTML, html_input "strip" statt "escape" (Ralf tippt hier
     * frei, versehentlich eingefügtes "<" soll nicht als kaputtes Tag im
     * Ergebnis auftauchen) - kein Freigabe-Workflow, Bearbeitung ist
     * Super-Admin-only, kein XSS-Risiko durch fremde Nutzer.
     */
    public function bodyHtml(): string
    {
        $body = preg_replace(
            self::IMAGE_PLACEHOLDER_PATTERN,
            "\n\n![]({$this->imageBaseUrl()}/$1)\n\n",
            (string) $this->body
        );

        $html = (string) Str::markdown($body, ['html_input' => 'strip']);

        $html = (string) preg_replace(
            self::BUTTON_QUOTE_PATTERN,
            '<span class="'.self::BUTTON_QUOTE_CLASSES.'">$1</span>',
            $html
        );

        $html = (string) preg_replace_callback(self::ROUTE_LINK_PATTERN, function (array $match): string {
            [, $routeName, $label] = $match;

            if (Route::has($routeName)) {
                try {
                    return '<a href="'.e(route($routeName)).'">'.$label.'</a>';
                } catch (Throwable) {
                    // Route erwartet Parameter (z.B. projekte.show) - aus
                    // generischem Hilfetext heraus nicht sinnvoll auflösbar,
                    // fällt unten durch wie ein unbekannter Routenname.
                }
            }

            return '<span class="text-red-500 underline decoration-dotted" title="'.e(__('Keine Seite mit diesem Routennamen gefunden.')).'">'.$label.'</span>';
        }, $html);

        // ((Begriff)) bzw. ((Begriff|Anzeigetext)) aus dem Begriffsverzeichnis
        $html = (string) preg_replace_callback('/\(\(([^()|<>]+?)(?:\|([^()<>]+?))?\)\)/u', fn (array $match): string => GlossaryTerm::link($match[1], $match[2] ?? null), $html);

        return (string) preg_replace_callback(self::ARTICLE_LINK_PATTERN, function (array $match): string {
            // "[[42]]" / "[[42|Linktext]]" (Hilfe-Nr. der Zielseite, Ralf, 2026-10-05) oder wie bisher "[[Titel]]" / "[[Titel|Linktext]]"
            [$reference, $alias] = array_pad(explode('|', $match[1], 2), 2, null);
            $reference = trim($reference);
            $alias = $alias !== null && trim($alias) !== '' ? trim($alias) : null;
            $notFound = fn (string $label, string $hint): string => '<span class="text-red-500 underline decoration-dotted" title="'.e($hint).'">'.e($label).'</span>';

            if (ctype_digit($reference)) {
                $article = HelpArticle::query()->with('translations')->find((int) $reference);
                $targetTranslation = $article?->translation($this->locale) ?? $article?->translation(HelpArticle::PRIMARY_LOCALE);
                if (! $article || ! $article->isVisibleTo(auth()->user())) {
                    return $notFound($alias ?? $reference, __('Keine Hilfeseite mit dieser Nummer gefunden.'));
                }

                return '<a href="#" data-help-key="'.e($article->key).'">'.e($alias ?? $targetTranslation?->title ?? $article->key).'</a>';
            }

            $targets = self::query()->where('locale', $this->locale)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($reference)])
                ->with('article')
                ->get();

            // Auch, wenn der Zielartikel existiert, aber für den gerade lesenden Nutzer nicht sichtbar ist
            // (visible_role) - genauso behandelt wie "nicht gefunden", kein Hinweis auf die Existenz einer
            // für ihn gesperrten Seite.
            $targets = $targets->filter(fn (self $target) => $target->article->isVisibleTo(auth()->user()))->values();
            if ($targets->count() > 1) {
                return $notFound($alias ?? $reference, __('Dieser Titel kommt mehrfach vor. Bitte statt des Titels die Hilfe-Nr. der Zielseite verwenden.'));
            }
            if ($targets->isEmpty()) {
                return $notFound($alias ?? $reference, __('Kein Hilfeartikel mit diesem Titel gefunden.'));
            }

            return '<a href="#" data-help-key="'.e($targets->first()->article->key).'">'.e($alias ?? $reference).'</a>';
        }, $html);
    }

    private function imageBaseUrl(): string
    {
        return rtrim(url('/images/hilfe'), '/');
    }
}
