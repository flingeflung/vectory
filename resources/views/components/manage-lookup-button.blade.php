{{--
    Kompakter Icon-Button ("Stift"), öffnet eines der globalen Verwalten-
    Overlays (Firma/Abteilung/Geschäftsbereich/Rolle) direkt, ohne erst eine
    Person aufrufen zu müssen. Bewusst nur ein Icon statt "verwalten"-Text -
    neben kurzen UND langen Filter-Labels (z.B. "Geschäftsbereich") bleibt
    die Spaltenbreite dadurch stabil, auch beim Umbrechen des Filterbereichs.
    Sieht trotzdem klar wie ein Button aus (umrandete Box), Tooltip nennt die
    genaue Aktion.
--}}
@props(['modal', 'title'])
<button
    type="button"
    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: {{ \Illuminate\Support\Js::from($modal) }} }))"
    title="{{ $title }}"
    class="shrink-0 rounded border border-gray-300 bg-gray-100 p-0.5 text-gray-500 hover:bg-gray-200 hover:text-gray-700"
>
    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
</button>
