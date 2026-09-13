@forelse ($products as $product)
    <tr class="hover:bg-gray-50">
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->product_number }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->name }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->productGroup?->number }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $product->productGroup?->name }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $product->extra_text ?? '–' }}</td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-700">
            @forelse ($product->projects as $linkedProject)
                <a
                    href="#"
                    onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $linkedProject->id }} } }))"
                    class="text-indigo-600 hover:underline"
                    title="{{ $linkedProject->title }}"
                >{{ $linkedProject->source_pn }}</a>{{ ! $loop->last ? ', ' : '' }}
            @empty
                <span class="text-gray-400">–</span>
            @endforelse
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('Keine Produkte gefunden.') }}</td>
    </tr>
@endforelse
