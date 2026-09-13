@foreach ($products as $product)
    <label data-product-row class="flex items-center gap-1.5 py-0.5 text-gray-700">
        <input
            type="checkbox"
            class="rounded border-gray-300"
            @change="toggle({{ $product->id }})"
        >
        <span class="font-medium">{{ $product->product_number }}</span>
        {{ $product->name }}
        <span class="text-xs text-gray-400">({{ $product->productGroup?->number }} {{ $product->productGroup?->name }})</span>
    </label>
@endforeach
