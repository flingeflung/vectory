<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hilfesystem (Ralf, 2026-09-12): "?" oben in der Topbar auf jeder Seite,
 * öffnet ein Panel mit Kontexthilfe zur aktuellen Seite + Stichwortsuche.
 * Für alle eingeloggten Nutzer verfügbar (nicht nur Admins) - erklärt
 * Vectory selbst. Pflege der Artikel läuft separat über
 * Admin\HelpArticleController (Super-Admin-only).
 */
class HelpController extends Controller
{
    /**
     * Liefert das austauschbare Ergebnis-Fragment im Hilfe-Panel: bei
     * Sucheingabe (?q=) die Trefferliste, sonst den Artikel zur aktuell
     * offenen Seite (?route=), falls einer existiert.
     */
    public function results(Request $request): View
    {
        $locale = app()->getLocale();
        $query = trim((string) $request->query('q', ''));

        if ($query !== '') {
            $results = HelpArticle::search($query, $locale)
                ->filter(fn (HelpArticle $article) => $article->isVisibleTo($request->user()))
                ->values();

            return view('help._results', [
                'mode' => 'search',
                'query' => $query,
                'results' => $results,
                'article' => null,
                'translation' => null,
            ]);
        }

        $routeName = (string) $request->query('route', '');
        $dialogId = (string) $request->query('dialog', '');
        $tab = (string) $request->query('tab', '');

        // Zuerst der Artikel zum Reiter des Dialogs ("D-1234#planung.auslastung", dann ein Reiter weiter oben "D-1234#planung"),
        // dann der zum Dialog selbst (Dialog-ID steht in route_names), sonst der zur Seite.
        $dialogArticle = null;
        if ($dialogId !== '') {
            foreach (HelpArticle::dialogKeys($dialogId, $tab) as $key) {
                $candidate = HelpArticle::query()->forRoute($key)->with('translations')->first();
                if ($candidate && $candidate->isVisibleTo($request->user())) {
                    $dialogArticle = $candidate;
                    break;
                }
            }
        }
        $article = $dialogArticle
            ?? ($routeName !== '' ? HelpArticle::query()->forRoute($routeName)->with('translations')->first() : null);

        if ($article && ! $article->isVisibleTo($request->user())) {
            $article = null;
        }

        return view('help._results', [
            'mode' => 'article',
            'query' => '',
            'results' => null,
            'article' => $article,
            'translation' => $article?->translation($locale),
            // Für den Super-Admin: fehlt hier ein Artikel, zeigt der leere
            // Zustand direkt den Routennamen an, den er bei "Seiten
            // (Routennamen)" in der Hilfeseiten-Verwaltung einträgt - sonst
            // müsste er dafür jedes Mal fragen, welche Route das gerade ist.
            'routeName' => $routeName,
            'dialogId' => $dialogId,
            'helpKey' => HelpArticle::dialogKeys($dialogId, $tab)[0] ?? $dialogId,
            'dialogHasArticle' => $dialogArticle !== null,
            'canManageHelp' => $request->user()?->isSuperAdmin(),
            'accessInfo' => $request->user()?->isSuperAdmin() ? \App\Support\HelpAccess::describe(HelpArticle::dialogKeys($dialogId, $tab), $routeName) : null,
        ]);
    }

    /**
     * Ein bestimmter Artikel, unabhängig von der aktuellen Seite - z.B.
     * nach Klick auf einen Suchtreffer.
     */
    public function show(Request $request, HelpArticle $helpArticle): View
    {
        abort_unless($helpArticle->isVisibleTo($request->user()), 404);

        $locale = app()->getLocale();

        return view('help._results', [
            'mode' => 'article',
            'query' => '',
            'results' => null,
            'article' => $helpArticle,
            'translation' => $helpArticle->translation($locale),
        ]);
    }
}
