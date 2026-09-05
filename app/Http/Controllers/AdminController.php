<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Einstiegspunkt für den künftigen Admin-Bereich (Rechtekonzept,
 * Rollen-/Rechteverwaltung) - bewusst erstmal ohne Inhalt, siehe Absprache
 * mit Ralf: Nav-Punkt schon mal da, Umsetzung folgt als eigene Aufgabe.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index');
    }
}
