<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpArticleTranslation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $tree = HelpArticle::tree();
        $flat = HelpArticle::flattenTree($tree);

        $selected = $request->filled('article')
            ? $flat->firstWhere('id', (int) $request->query('article'))
            : null;

        return view('admin.help-articles.index', [
            'tree' => $tree,
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

        $nextPosition = 1 + (int) HelpArticle::query()->whereNull('parent_id')->max('position');

        $article = HelpArticle::query()->create([
            'key' => $this->uniqueKey($title),
            'route_names' => [],
            'parent_id' => null,
            'position' => $nextPosition,
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

        $visibleRole = $request->string('visible_role')->toString() ?: null;
        abort_if($visibleRole !== null && ! array_key_exists($visibleRole, HelpArticle::VISIBILITY_LEVELS), 422);

        $helpArticle->update(['route_names' => $routeNames, 'visible_role' => $visibleRole]);

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

    /**
     * Vorschau des gerade bearbeiteten (noch nicht gespeicherten) Titels/
     * Texts - Ralf: "kurz die aktuell in Bearbeitung befindliche Seite
     * visuell testen, ohne zu Speichern". Baut ein NICHT gespeichertes
     * HelpArticleTranslation-Objekt und lässt es durch dieselbe
     * bodyHtml()-Logik wie einen echten Artikel laufen (Bild-Platzhalter,
     * {Button}-Zitate, [[Verweise]] auf andere Artikel, route:-Links).
     */
    public function preview(Request $request): View
    {
        $translation = new HelpArticleTranslation([
            'title' => (string) $request->string('title'),
            'body' => (string) $request->string('body'),
            'locale' => app()->getLocale(),
        ]);

        return view('help._article', ['translation' => $translation]);
    }

    public function destroy(HelpArticle $helpArticle): RedirectResponse
    {
        // Kinder werden NICHT mitgelöscht - rücken per nullOnDelete (siehe
        // Migration) automatisch auf Ebene 1 auf, statt Inhalte beim
        // Aufräumen der Struktur zu verlieren.
        $helpArticle->delete();

        return redirect()->route('admin.hilfeseiten')->with('status', 'help-article-deleted');
    }

    /**
     * Reine Sortierung innerhalb derselben Ebene (Ziehen am Griff-Symbol,
     * kein Ebenenwechsel) - analog ProjectTypeController::reorderMain().
     */
    public function reorder(Request $request): RedirectResponse
    {
        $parentId = $request->filled('parent_id') ? (int) $request->input('parent_id') : null;

        collect($request->array('ids'))->values()->each(function (string $id, int $index) use ($parentId) {
            HelpArticle::query()->where('id', (int) $id)->where('parent_id', $parentId)->update(['position' => $index]);
        });

        return redirect()->route('admin.hilfeseiten');
    }

    /**
     * Macht den Artikel zum letzten Kind seines direkten Vorgängers auf
     * derselben Ebene - kein Vorgänger vorhanden (erster Eintrag) oder
     * würde MAX_DEPTH überschreiten: keine Wirkung.
     */
    public function indent(HelpArticle $helpArticle): RedirectResponse
    {
        $previousSibling = HelpArticle::query()
            ->where('parent_id', $helpArticle->parent_id)
            ->where('position', '<', $helpArticle->position)
            ->orderByDesc('position')
            ->first();

        if ($previousSibling && $previousSibling->depth() < HelpArticle::MAX_DEPTH) {
            DB::transaction(function () use ($helpArticle, $previousSibling) {
                $this->renumberSiblingsAfterRemoval($helpArticle);

                $helpArticle->update([
                    'parent_id' => $previousSibling->id,
                    'position' => $previousSibling->children()->count(),
                ]);
            });
        }

        return redirect()->route('admin.hilfeseiten', ['article' => $helpArticle->id]);
    }

    /**
     * Macht den Artikel zum Geschwister seines bisherigen Elternteils,
     * direkt danach einsortiert - bereits auf Ebene 1: keine Wirkung.
     */
    public function outdent(HelpArticle $helpArticle): RedirectResponse
    {
        if ($helpArticle->parent_id === null) {
            return redirect()->route('admin.hilfeseiten', ['article' => $helpArticle->id]);
        }

        $oldParent = $helpArticle->parent;

        DB::transaction(function () use ($helpArticle, $oldParent) {
            $this->renumberSiblingsAfterRemoval($helpArticle);

            HelpArticle::query()
                ->where('parent_id', $oldParent->parent_id)
                ->where('position', '>', $oldParent->position)
                ->increment('position');

            $helpArticle->update([
                'parent_id' => $oldParent->parent_id,
                'position' => $oldParent->position + 1,
            ]);
        });

        return redirect()->route('admin.hilfeseiten', ['article' => $helpArticle->id]);
    }

    /**
     * Schließt die Lücke, die ein Artikel beim Verlassen seiner bisherigen
     * Geschwisterliste hinterlässt (gemeinsam von indent()/outdent()
     * gebraucht) - sonst driftet die Positions-Nummerierung mit jeder
     * Verschiebung weiter auseinander.
     */
    private function renumberSiblingsAfterRemoval(HelpArticle $helpArticle): void
    {
        HelpArticle::query()
            ->where('parent_id', $helpArticle->parent_id)
            ->where('position', '>', $helpArticle->position)
            ->decrement('position');
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
