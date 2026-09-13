<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Produkte') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <form method="GET" action="{{ route('produkte') }}" class="mb-3 flex shrink-0 flex-wrap items-center gap-3 text-sm">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">

                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('Suche') }}:</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="{{ __('Produktnr., -bezeichnung, Gruppe...') }}"
                        oninput="window.liveFilterSearch(this, 'produkte-list')"
                        class="w-64 rounded-md border-gray-300 py-1 text-sm"
                    >
                </label>
            </form>

            <div id="produkte-list" class="flex flex-1 min-h-0 flex-col">
                <div class="mb-3 shrink-0 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    {{ __('gefunden: :total, angezeigt: :shown', ['total' => $total, 'shown' => min($total, $products->count())]) }}
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg flex flex-1 min-h-0 flex-col">
                    <div class="flex-1 min-h-0 overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-sortable-th field="product_number" :sort="$sort" :direction="$direction">{{ __('Produktnr.') }}</x-sortable-th>
                                    <x-sortable-th field="name" :sort="$sort" :direction="$direction">{{ __('Produktbezeichnung') }}</x-sortable-th>
                                    <x-sortable-th field="group_number" :sort="$sort" :direction="$direction">{{ __('Produktgruppennr.') }}</x-sortable-th>
                                    <x-sortable-th field="group_name" :sort="$sort" :direction="$direction">{{ __('Produktgruppenbezeichnung') }}</x-sortable-th>
                                    <x-sortable-th field="extra_text" :sort="$sort" :direction="$direction">{{ __('Produktzusatztext') }}</x-sortable-th>
                                    <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Projektverknüpfung') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
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
                            </tbody>
                        </table>
                    </div>

                    @if ($hasMore)
                        <div class="shrink-0 border-t border-gray-200 p-3 text-center">
                            <a
                                href="{{ request()->fullUrlWithQuery(['limit' => $limit + 500]) }}"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >
                                {{ __('Weitere laden (:count von :total geladen)', ['count' => $products->count(), 'total' => $total]) }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
