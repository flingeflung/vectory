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

        return (string) Str::markdown($body, ['html_input' => 'strip']);
    }

    private function imageBaseUrl(): string
    {
        return rtrim(url('/images/hilfe'), '/');
    }
}
