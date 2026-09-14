{{-- "Projektverbund" (Ralf, 2026-09-14): markiert ein Projekt als
     Unterprojekt eines Verbunds - Pfeil-nach-unten-rechts als "gehört zu"-
     Metapher, Tooltipp nennt das Hauptprojekt. Rendert nichts außerhalb
     einer Verbund-Rolle. --}}
@props(['project'])

@if ($project->verbund_rolle === 2)
    <span class="inline-block align-middle text-sky-600" title="{{ __('Unterprojekt von :name', ['name' => $project->hauptprojekt?->source_pn.' – '.$project->hauptprojekt?->title]) }}">
        <svg class="inline h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 20 20">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v8a3 3 0 003 3h6m0 0l-3-3m3 3l-3 3" />
        </svg>
    </span>
@endif
