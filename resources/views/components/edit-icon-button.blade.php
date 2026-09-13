{{--
    Kompakter Stift-Icon-Button ("Ändern") - Ralf, 2026-09-13: "Ich bin
    generell Fan von kleinen Symbolen, die man immer wieder verwenden kann
    und die nicht so aufdringlich sind wie Textbuttons." Ersetzt die
    bisherigen "Ändern"-Textbuttons (Markt/Workflow/Projektbeteiligte
    Personen/Produkt-Verknüpfung in den Projektdetails).

    Zwei Verwendungsarten:
    - `modal`-Prop gesetzt: öffnet direkt das benannte Overlay (bisheriges
      Verhalten dieser Komponente, vormals manage-lookup-button.blade.php).
    - `modal` weggelassen: der Aufrufer übergibt sein eigenes @click (z.B.
      um einen lokalen Alpine-Zustand wie "editingMarkets" umzuschalten) -
      landet automatisch in $attributes.
--}}
@props(['modal' => null, 'title'])
<button
    type="button"
    @if ($modal)
        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: {{ \Illuminate\Support\Js::from($modal) }} }))"
    @endif
    {{ $attributes->merge(['title' => $title, 'class' => 'shrink-0 rounded border border-gray-300 bg-btn-secondary p-0.5 text-gray-500 hover:bg-btn-secondary-hover hover:text-gray-700']) }}
>
    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
    </svg>
</button>
