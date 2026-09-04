@props(['label'])

{{-- Leichtgewichtiges Auswahl-Popup statt dauerhaft ausgeklappter
     Checkbox-Liste (wie in Vietto: "Statusauswahl"/"Illustrator" öffnen
     ein kleines Fenster statt alles offen im Filterbalken zu zeigen). --}}
<div class="relative inline-block" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button
        type="button"
        @click="open = !open"
        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
    >
        {{ $label }}
        <svg class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="absolute left-0 z-20 mt-1 w-64 rounded-md border border-gray-200 bg-white p-3 text-sm shadow-lg"
    >
        {{ $slot }}
    </div>
</div>
