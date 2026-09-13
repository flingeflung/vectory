{{--
    Wird als reines HTML-Fragment in #product-picker-body-{project} geladen
    (siehe system_model.blade.php, runSearch()), nicht als eigenständige
    Seite - deshalb kein <x-modal> hier, das bleibt außenrum stehen.
--}}
<div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Verknüpft') }}</div>
<div class="mb-3">
    @forelse ($linkedProducts as $product)
        <label data-product-row class="flex items-center gap-1.5 py-0.5 text-gray-700">
            <input type="checkbox" checked class="rounded border-gray-300" @change="toggle({{ $product->id }})">
            <span class="font-medium">{{ $product->product_number }}</span>
            {{ $product->name }}
            <span class="text-xs text-gray-400">({{ $product->productGroup?->number }} {{ $product->productGroup?->name }})</span>
        </label>
    @empty
        <div class="text-xs text-gray-400">{{ __('Keine Produkte verknüpft.') }}</div>
    @endforelse
</div>

<div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Andere Produkte') }}</div>
<div id="product-picker-other-rows">
    @if ($otherProducts->isEmpty())
        <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
    @else
        @include('projekte.partials.product-picker-rows', ['products' => $otherProducts])
    @endif
</div>
<div
    x-show="otherHasMore"
    x-cloak
    x-init="initScrollSentinel($el)"
    class="py-1.5 text-center text-[11px] text-gray-400"
    data-has-more="{{ $otherHasMore ? '1' : '0' }}"
>
    <span x-show="loadingMore" x-cloak>{{ __('Lädt…') }}</span>
</div>
