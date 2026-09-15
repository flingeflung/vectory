{{-- "Projektverbund" (Ralf, 2026-09-14): markiert ein Projekt als
     Hauptprojekt eines Verbunds - Fahnen-Symbol als "Anker"-Metapher, damit
     es sich klar vom favorite-star (persönlicher Favorit, andere Bedeutung)
     unterscheidet. Rendert nichts außerhalb einer Verbund-Rolle. --}}
@props(['project'])

@if ($project->verbund_rolle === 1)
    <span class="inline-block align-middle text-indigo-600" title="{{ __('Hauptprojekt eines Verbunds') }}">⚑</span>
@endif
