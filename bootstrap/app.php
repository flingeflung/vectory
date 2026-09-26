<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Freigabe-Mail-Links (ohne Login, siehe routes/web.php): ungültige/
        // abgelaufene Signatur als verständliche Seite statt Weiterleitung
        // zum Login, mit dem der externe Empfänger nichts anfangen kann.
        // Muss VOR dem generischen HttpException-Handler stehen.
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, Request $request) {
            if (! $request->is('freigabe/*')) {
                return null;
            }

            return response()->view('freigabe.done', [
                'title' => __('Link ungültig'),
                'message' => __('Dieser Link ist ungültig oder abgelaufen. Bitte fordern Sie beim Absender eine neue Freigabe-Mail an.'),
            ], 403);
        });

        // abort_if()/abort_unless() mit einer Nachricht (z.B. Lösch-Schutz
        // "wird bereits in Projekten verwendet") landen sonst als rohe
        // Debug-/Fehlerseite statt einer verständlichen Rückmeldung -
        // stattdessen zurück zur vorherigen Seite mit Hinweis-Dialog (siehe
        // layouts/app.blade.php, window.notifyDialog). Nur bei 4xx +
        // vorhandener Nachricht, sonst normales Fehlerverhalten (z.B. 404).
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->expectsJson() || $e->getStatusCode() < 400 || $e->getStatusCode() >= 500 || $e->getMessage() === '') {
                return null;
            }

            // Ralf-Bug-Report, 2026-09-18: ERR_TOO_MANY_REDIRECTS - back()
            // landete auf genau der URL, die gerade erst mit dieser
            // Exception fehlgeschlagen ist (die "vorherige" Seite WAR die
            // aktuelle), jeder erneute Aufruf scheiterte identisch ->
            // Endlosschleife. Zielt "zurück" auf dieselbe URL wie die
            // aktuelle, stattdessen zu einer garantiert ungated Startseite.
            $target = url()->previous();
            if ($target === $request->fullUrl()) {
                return redirect()->route('dashboard')->with('error', $e->getMessage());
            }

            return redirect()->back()->with('error', $e->getMessage());
        });
    })->create();
