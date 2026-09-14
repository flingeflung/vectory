{{-- "Projektverbund" (Ralf, 2026-09-14): markiert ein Projekt als
     Hauptprojekt eines Verbunds - Fahnen-Symbol als "Anker"-Metapher, damit
     es sich klar vom favorite-star (persönlicher Favorit, andere Bedeutung)
     unterscheidet. Rendert nichts außerhalb einer Verbund-Rolle. --}}
@props(['project'])

@if ($project->verbund_rolle === 1)
    <span class="inline-block align-middle text-indigo-600" title="{{ __('Hauptprojekt eines Verbunds') }}">
        <svg class="inline h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 3v14M4 4h9l-2 3 2 3H4" />
        </svg>
    </span>
@endif
