{{--
    Kompakter Info-Icon-Button ("Details ansehen") - gleiches Prinzip wie
    edit-icon-button.blade.php (Ralf: "kleine Symbole, die man immer wieder
    verwenden kann"), nur für rein lesende Detail-Overlays statt Bearbeiten.
    @click/onclick kommt vom Aufrufer, landet automatisch in $attributes.
--}}
@props(['title'])
<button
    type="button"
    {{ $attributes->merge(['title' => $title, 'class' => 'shrink-0 rounded border border-gray-300 bg-btn-secondary p-0.5 text-gray-500 hover:bg-btn-secondary-hover hover:text-gray-700']) }}
>
    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
</button>
