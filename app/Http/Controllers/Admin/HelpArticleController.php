<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Verwaltung der Hilfeartikel (Ralf, 2026-09-12: "direkt DB-Verwaltung,
 * damit ich das unabhängig von dir pflegen kann") - Super-Admin-only, weil
 * die Inhalte mandantenübergreifend gelten (siehe HelpArticle-Docblock).
 * Struktur analog zu MailTemplateController (links Liste, rechts Detail),
 * hier zusätzlich mit Sprach-Reitern statt einem einzelnen Textfeld je
 * Feld.
 */
class HelpArticleController extends Controller
{
    public function index(Request $request): View
    {
        $articles = HelpArticle::query()->with('translations')->get()
            ->sortBy(fn (HelpArticle $article) => $article->translation(HelpArticle::PRIMARY_LOCALE)?->title ?? $article->key)
            ->values();

        $selected = $request->filled('article')
            ? $articles->firstWhere('id', (int) $request->query('article'))
            : null;

        return view('admin.help-articles.index', [
            'articles' => $articles,
            'selected' => $selected,
            'locales' => HelpArticle::AVAILABLE_LOCALES,
        ]);
    }

    /**
     * Nur der Titel (Quellsprache) ist beim Anlegen Pflicht - Rest wird
     * direkt danach rechts ausgefüllt, gleiches Muster wie
     * MailTemplateController::store().
     */
    public function store(Request $request): RedirectResponse
    {
        $title = trim((string) $request->string('title'));
        abort_if($title === '', 422);

        $article = HelpArticle::query()->create([
            'key' => $this->uniqueKey($title),
            'route_names' => [],
        ]);

        $article->translations()->create([
            'locale' => HelpArticle::PRIMARY_LOCALE,
            'title' => $title,
            'body' => '',
        ]);

        return redirect()->route('admin.hilfeseiten', ['article' => $article->id])->with('status', 'help-article-updated');
    }

    /**
     * "key" ist rein interne Link-Adresse (siehe HelpArticle-Docblock),
     * wird beim Anlegen einmalig erzeugt (uniqueKey()) und danach nie mehr
     * angefasst/angezeigt - Ralf: "warum muss ich das sehen" - Bedeutung
     * erklären UND Dopplungs-Risiko fallen damit beide weg.
     */
    public function update(Request $request, HelpArticle $helpArticle): RedirectResponse
    {
        // Freie Routennamen-Liste (ein Name pro Zeile oder Komma-getrennt) -
        // bewusst kein Pulldown über alle bekannten Routen, die Liste wäre
        // riesig und meist irrelevant; Ralf kennt die Routennamen aus dem
        // Zusammenhang mit mir.
        $routeNames = collect(preg_split('/[\r\n,]+/', (string) $request->string('route_names')))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $helpArticle->update(['route_names' => $routeNames]);

        foreach (HelpArticle::AVAILABLE_LOCALES as $locale => $label) {
            $title = trim((string) $request->string("translations.{$locale}.title"));
            $keywords = trim((string) $request->string("translations.{$locale}.keywords"));
            $body = (string) $request->string("translations.{$locale}.body");

            if ($title === '' && $locale !== HelpArticle::PRIMARY_LOCALE) {
                // Leerer Titel in einer Nicht-Quellsprache = "noch nicht
                // übersetzt" - vorhandene Übersetzung wird dann entfernt,
                // Fallback greift wieder auf die Quellsprache zurück.
                $helpArticle->translations()->where('locale', $locale)->delete();

                continue;
            }

            if ($title === '') {
                continue;
            }

            $helpArticle->translations()->updateOrCreate(
                ['locale' => $locale],
                ['title' => $title, 'keywords' => $keywords ?: null, 'body' => $body]
            );
        }

        return redirect()->route('admin.hilfeseiten', ['article' => $helpArticle->id])->with('status', 'help-article-updated');
    }

    public function destroy(HelpArticle $helpArticle): RedirectResponse
    {
        $helpArticle->delete();

        return redirect()->route('admin.hilfeseiten')->with('status', 'help-article-deleted');
    }

    private function uniqueKey(string $title): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $key = $base;
        $suffix = 2;

        while (HelpArticle::query()->where('key', $key)->exists()) {
            $key = "{$base}-{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
