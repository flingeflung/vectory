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
            self::applyAudience((string) $this->body, auth()->user())
        );

        $html = (string) Str::markdown($body, ['html_input' => 'strip']);

        // Codebeispiele ("`[[42]]`", "`((Begriff))`") sollen als Text stehen bleiben und nicht zu Links werden.
        $codeBlocks = [];
        $html = (string) preg_replace_callback('/<code>.*?<\/code>/s', function (array $match) use (&$codeBlocks): string {
            $codeBlocks[] = $match[0];

            return '@@CODE'.(count($codeBlocks) - 1).'@@';
        }, $html);

        // Kürzel wie "z. B." und Zahl-Einheit-Paare wie "10 Std." dürfen nicht mitten im Ausdruck umbrechen (Ralf, 2026-10-09):
        // beim Ausliefern geschützte Leerzeichen einsetzen. Der gespeicherte Text bleibt unverändert, Code-Beispiele sind oben bereits herausgenommen.
        $html = self::protectSpaces($html);

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

        $html = (string) preg_replace_callback(self::ARTICLE_LINK_PATTERN, function (array $match): string {
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

        return (string) preg_replace_callback('/@@CODE(\d+)@@/', fn (array $match): string => $codeBlocks[(int) $match[1]], $html);
    }

    /**
     * Passagen nur für bestimmte Stufen (Ralf, 2026-10-09): [[Admin3|Text]] zeigt den Text allen drei Admin-Stufen, [[Admin2|Text]] nur
     * Zentral-Admin und Super-Admin, [[Admin1|Text]] nur dem Super-Admin. Für alle anderen verschwindet die ganze Passage samt Markierung, auch
     * die leere Zeile, die dabei entstünde. Im Text dürfen Hilfe-Verweise wie [[82|Titel]] stehen (geschachtelt).
     */
    public static function applyAudience(string $text, ?User $user): string
    {
        $levels = [
            1 => fn () => $user?->isSuperAdmin() ?? false,
            2 => fn () => $user?->canAccessAllOrganizations() ?? false,
            3 => fn () => $user?->isAdmin() ?? false,
        ];

        // Codebeispiele in Backticks (z. B. die Beschreibung der Markierung selbst) bleiben unverändert
        $codeSpans = [];
        $text = (string) preg_replace_callback('/`[^`\n]*`/', function (array $match) use (&$codeSpans): string {
            $codeSpans[] = $match[0];

            return "\u{E000}".(count($codeSpans) - 1)."\u{E001}";
        }, $text);

        $offset = 0;
        while (preg_match('/\[\[Admin([123])\|/i', $text, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $innerStart = $start + strlen($match[0][0]);

            // passendes Ende suchen; innere "[[ ... ]]" zählen mit
            $depth = 1;
            $position = $innerStart;
            $length = strlen($text);
            while ($position < $length && $depth > 0) {
                if (substr($text, $position, 2) === '[[') {
                    $depth++;
                    $position += 2;
                } elseif (substr($text, $position, 2) === ']]') {
                    $depth--;
                    $position += 2;
                } else {
                    $position++;
                }
            }
            if ($depth !== 0) {
                break; // Markierung nicht geschlossen: Text unverändert lassen
            }

            $inner = substr($text, $innerStart, $position - 2 - $innerStart);
            if ($levels[(int) $match[1][0]]()) {
                $text = substr($text, 0, $start).$inner.substr($text, $position);
                $offset = $start;

                continue;
            }

            // Nicht sichtbar: Passage entfernen; steht sie allein in der Zeile, auch die Zeile
            $lineStart = strrpos(substr($text, 0, $start), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $lineEnd = strpos($text, "\n", $position);
            $lineEnd = $lineEnd === false ? $length : $lineEnd;
            $before = substr($text, $lineStart, $start - $lineStart);
            $after = substr($text, $position, $lineEnd - $position);
            if (trim($before) === '' && trim($after) === '') {
                $text = substr($text, 0, $lineStart).substr($text, min($lineEnd + 1, $length));
                $offset = $lineStart;
            } else {
                $text = substr($text, 0, $start).substr($text, $position);
                $offset = $start;
            }
        }

        return (string) preg_replace_callback("/\u{E000}(\d+)\u{E001}/u", fn (array $match): string => $codeSpans[(int) $match[1]], $text);
    }

    /** Kürzel, deren Leerzeichen zu geschützten Leerzeichen werden. */
    private const PROTECTED_ABBREVIATIONS = ['z. B.', 'd. h.', 'u. a.', 'u. U.', 'u. Ä.', 'u. v. m.', 'i. d. R.', 'i. A.', 'i. V.', 's. o.', 's. u.', 'o. ä.', 'v. a.', 'z. T.', 'z. Z.'];

    /** Einheiten, die nach einer Zahl an dieser hängen bleiben. */
    private const PROTECTED_UNITS = 'Std\.|h|min|Min\.|Uhr|Tage?n?|Wochen?|Monate?n?|Jahre?n?|%|€|EUR|mm|cm|kg|MB|GB|px';

    /** Setzt in Kürzeln und zwischen Zahl und Einheit geschützte Leerzeichen, nur in Textteilen (nie in HTML-Tags). */
    private static function protectSpaces(string $html): string
    {
        $nbsp = "\u{00A0}";
        $abbreviations = implode('|', array_map(fn (string $abbr) => str_replace(' ', '[ \x{00A0}]', preg_quote($abbr, '/')), self::PROTECTED_ABBREVIATIONS));

        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        foreach ($parts as $index => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }
            $part = (string) preg_replace_callback('/(?<![\p{L}])(?:'.$abbreviations.')/iu', fn (array $match): string => (string) preg_replace('/[ \x{00A0}]/u', $nbsp, $match[0]), $part);
            $part = (string) preg_replace('/(\d)[ ](?=(?:'.self::PROTECTED_UNITS.')(?![\p{L}]))/u', '$1'.$nbsp, $part);
            $part = (string) preg_replace('/\b(Nr\.|Abs\.|Kap\.|Abb\.)[ ](?=\d)/u', '$1'.$nbsp, $part);
            $parts[$index] = $part;
        }

        return implode('', $parts);
    }

    private function imageBaseUrl(): string
    {
        return rtrim(url('/images/hilfe'), '/');
    }
}
