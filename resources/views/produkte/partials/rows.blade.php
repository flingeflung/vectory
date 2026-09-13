@forelse ($products as $product)
    <tr class="hover:bg-gray-50">
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->product_number }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->name }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->productGroup?->number }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->productGroup?->name }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $product->extra_text ?? '–' }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-400">–</td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('Keine Produkte gefunden.') }}</td>
    </tr>
@endforelse
