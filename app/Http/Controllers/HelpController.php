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
            return view('help._results', [
                'mode' => 'search',
                'query' => $query,
                'results' => HelpArticle::search($query, $locale),
                'article' => null,
                'translation' => null,
            ]);
        }

        $routeName = (string) $request->query('route', '');
        $article = $routeName !== ''
            ? HelpArticle::query()->forRoute($routeName)->with('translations')->first()
            : null;

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
            'canManageHelp' => $request->user()?->role === 'super_admin',
        ]);
    }

    /**
     * Ein bestimmter Artikel, unabhängig von der aktuellen Seite - z.B.
     * nach Klick auf einen Suchtreffer.
     */
    public function show(HelpArticle $helpArticle): View
    {
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
