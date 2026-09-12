<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

        return (string) preg_replace(
            self::BUTTON_QUOTE_PATTERN,
            '<span class="'.self::BUTTON_QUOTE_CLASSES.'">$1</span>',
            $html
        );
    }

    private function imageBaseUrl(): string
    {
        return rtrim(url('/images/hilfe'), '/');
    }
}
