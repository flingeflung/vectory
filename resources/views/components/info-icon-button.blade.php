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
    <x-icon name="info" class="h-4 w-4" />
</button>
