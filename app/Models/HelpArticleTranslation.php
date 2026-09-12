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
     * Markdown -> HTML, html_input "strip" statt "escape" (Ralf tippt hier
     * frei, versehentlich eingefügtes "<" soll nicht als kaputtes Tag im
     * Ergebnis auftauchen) - kein Freigabe-Workflow, Bearbeitung ist
     * Super-Admin-only, kein XSS-Risiko durch fremde Nutzer.
     */
    public function bodyHtml(): string
    {
        return (string) Str::markdown((string) $this->body, ['html_input' => 'strip']);
    }
}
