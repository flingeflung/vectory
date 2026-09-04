<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projekte') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div id="projekte-content" class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <div class="mb-3 flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-modal', 'projektfilter')"
                    class="inline-flex items-center rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-50 {{ ! empty($filters) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-300 bg-white text-gray-700' }}"
                >
                    {{ __('Projektfilter') }}
                    @if (! empty($filters))
                        <span class="ml-1.5 rounded-full bg-indigo-600 px-1.5 text-xs text-white">{{ count($filters) }}</span>
                    @endif
                </button>

                @if (! empty($filters))
                    <a href="{{ route('projekte', array_filter(['sort' => $sort, 'direction' => $direction, 'projektfilter_submitted' => 1])) }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('Filter zurücksetzen') }}
                    </a>
                @endif

                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-modal', 'anzeigefilter')"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    {{ __('Anzeigefilter') }}
                </button>
            </div>

            <div class="mb-3 shrink-0 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span>{{ __('Projekte gesamt') }}: <strong class="text-gray-800">{{ $totalCount }}</strong></span>
                    @if (! empty($filters))
                        <span>{{ __('gefiltert') }}: <strong class="text-gray-800">{{ $projects->total() }}</strong></span>
                    @endif
                </div>

                @if (! empty($filters['schnellsuche'] ?? null))
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <span class="text-gray-500">{{ __('Schnellsuche') }}:</span>
                        <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">
                            "{{ $filters['schnellsuche'] }}"
                            <a href="{{ route('projekte') }}" class="text-indigo-400 hover:text-indigo-700" title="{{ __('Schnellsuche verlassen') }}">&times;</a>
                        </span>
                    </div>
                @elseif (! empty($filterChips))
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <span class="text-gray-500">{{ __('Filter') }}:</span>
                        @foreach ($filterChips as $chip)
                            <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">
                                {{ $chip['label'] }}: {{ $chip['value'] }}
                                <a
                                    href="{{ route('projekte', array_filter(['sort' => $sort, 'direction' => $direction, 'projektfilter_submitted' => 1, 'filter' => collect($filters)->except($chip['key'])->all()])) }}"
                                    class="text-indigo-400 hover:text-indigo-700"
                                    title="{{ __('Filter entfernen') }}"
                                >&times;</a>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg flex flex-1 min-h-0 flex-col">
                <div class="flex-1 min-h-0 overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <x-sortable-th field="source_pn" :sort="$sort" :direction="$direction">{{ __('PN') }}</x-sortable-th>
                                @foreach ($columns as $column)
                                    @if (in_array($column['key'], ['title', 'version', 'status', 'workflow'], true))
                                        <x-sortable-th :field="$column['key']" :sort="$sort" :direction="$direction">{{ $column['label'] }}</x-sortable-th>
                                    @else
                                        <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ $column['label'] }}</th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($projects as $project)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-500">
                                        <span class="inline-flex items-center gap-1">
                                            <x-pn-link :project="$project" :sort="$sort" :direction="$direction" :filters="$filters" />
                                            @if (in_array($project->id, $favoriteProjectIds, true))
                                                <x-favorite-star :project="$project" :is-favorite="true" size="h-3.5 w-3.5" />
                                            @endif
                                        </span>
                                    </td>
                                    @foreach ($columns as $column)
                                        <td class="px-4 py-2 {{ ($column['icons'] ?? false) ? 'text-gray-500' : ($column['long_text'] ? 'text-gray-900 max-w-xs' : 'whitespace-nowrap text-gray-500') }}">
                                            @if ($column['type_icon'] ?? false)
                                                @php $typeSub = $project->project_type_sub_model; @endphp
                                                @if ($typeSub)
                                                    <div class="flex items-center gap-2">
                                                        @if ($typeSub->symbol)
                                                            <img src="{{ asset('images/project-type-icons/'.$typeSub->symbol) }}" alt="" class="h-5 w-auto shrink-0">
                                                        @endif
                                                        <div class="leading-tight">
                                                            <div class="text-xs text-gray-500">{{ $typeSub->main->name }}:</div>
                                                            <div class="text-gray-900">{{ $typeSub->name }}</div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">&ndash;</span>
                                                @endif
                                            @elseif ($column['graphic_summary'] ?? false)
                                                @php
                                                    $summary = $graphicOrderSummaries->get($project->id);
                                                    $goTotal = $summary->total ?? 0;
                                                    $goDone = $summary->done ?? 0;
                                                    $goImages = $summary->images ?? 0;
                                                @endphp
                                                <span title="{{ $goTotal }} {{ $goTotal == 1 ? __('Illustrationsauftrag') : __('Illustrationsaufträge') }}, {{ $goDone }} {{ __('erledigt') }}, {{ $goImages }} {{ $goImages == 1 ? __('Bild') : __('Bilder') }} {{ __('ges.') }}">{{ $goTotal }}/{{ $goDone }}/{{ $goImages }}</span>
                                            @elseif ($column['progress'] ?? false)
                                                @php $progress = $project->progressPercent(); @endphp
                                                @if ($progress !== null)
                                                    <div class="flex items-center gap-1.5" title="{{ $progress }} %">
                                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200">
                                                            <div class="h-full rounded-full bg-blue-500" style="width: {{ $progress }}%"></div>
                                                        </div>
                                                        <span class="text-xs">{{ $progress }}%</span>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">&ndash;</span>
                                                @endif
                                            @elseif ($column['icons'] ?? false)
                                                @php $marketPreviewCount = 5; $marketList = $project->markets; @endphp
                                                @if ($marketList->count() > $marketPreviewCount)
                                                    <span x-data="{ expanded: false }">
                                                        <span
                                                            x-show="!expanded"
                                                            @click="expanded = true"
                                                            class="inline-flex max-w-[180px] flex-wrap items-center gap-y-0.5 cursor-pointer"
                                                            title="{{ __('Klicken zum Erweitern') }}"
                                                        >
                                                            @foreach ($marketList->take($marketPreviewCount) as $market)
                                                                <x-market-icon :market="$market" />
                                                            @endforeach
                                                            <span class="text-[10px] text-gray-400">+{{ $marketList->count() - $marketPreviewCount }} {{ __('weitere') }}</span>
                                                        </span>
                                                        <span
                                                            x-show="expanded"
                                                            x-cloak
                                                            @click="expanded = false"
                                                            class="inline-flex max-w-[220px] flex-wrap items-center gap-y-0.5 cursor-pointer"
                                                        >
                                                            @foreach ($marketList as $market)
                                                                <x-market-icon :market="$market" />
                                                            @endforeach
                                                        </span>
                                                    </span>
                                                @else
                                                    @foreach ($marketList as $market)
                                                        <x-market-icon :market="$market" />
                                                    @endforeach
                                                @endif
                                            @else
                                                @php $value = $project->columnValue($column['key']); @endphp
                                                @if ($column['long_text'] && ! $column['show_long_text'] && \Illuminate\Support\Str::length((string) $value) > $column['short_length'])
                                                    <span x-data="{ expanded: false }">
                                                        <span
                                                            x-show="!expanded"
                                                            @click="expanded = true"
                                                            class="cursor-pointer"
                                                            title="{{ __('Klicken zum Erweitern') }}"
                                                        >{{ \Illuminate\Support\Str::limit($value, $column['short_length']) }}</span>
                                                        <span
                                                            x-show="expanded"
                                                            x-cloak
                                                            @click="expanded = false"
                                                            class="cursor-pointer"
                                                        >{{ $value }}</span>
                                                    </span>
                                                @else
                                                    {{ $value }}
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) + 1 }}" class="px-4 py-6 text-center text-gray-500">{{ __('Keine Projekte vorhanden.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="shrink-0 px-4 py-3 border-t border-gray-100">
                    {{ $projects->links() }}
                </div>
            </div>
        </div>
    </div>

    <x-modal name="anzeigefilter" max-width="xl" :show="$errors->any()" :dirty-check="'anzeigefilterIsDirty'">
        @include('projekte.partials.anzeigefilter-form')
    </x-modal>

    <x-modal name="projektfilter" max-width="xl" :dirty-check="'projektfilterIsDirty'">
        @include('projekte.partials.projektfilter-form')
    </x-modal>

    <script>
        (function () {
            const form = () => document.getElementById('anzeigefilter-form');
            let savedSnapshot = null;

            const serializeForm = (f) => f ? new URLSearchParams(new FormData(f)).toString() : null;
            const snapshot = () => { savedSnapshot = serializeForm(form()); };

            window.anzeigefilterIsDirty = () => {
                const current = serializeForm(form());
                return current !== null && current !== savedSnapshot;
            };

            // Deckt zwei Fälle ab: Button-Klick (Modal wird gerade geöffnet)
            // und Neuladen mit bereits offenem Modal (Validierungsfehler).
            // Zwei Microtask-Ticks, weil die Spaltenliste erst von Alpine
            // (x-for/x-if) ins DOM gerendert wird, nicht schon beim reinen
            // HTML-Parsing. requestAnimationFrame wäre hier riskant: Browser
            // setzen rAF in Hintergrund-Tabs aus, Microtasks nicht.
            const snapshotWhenSettled = () => queueMicrotask(() => queueMicrotask(snapshot));

            window.addEventListener('open-modal', (event) => {
                if (event.detail === 'anzeigefilter') {
                    snapshotWhenSettled();
                }
            });

            if (document.readyState === 'complete') {
                snapshotWhenSettled();
            } else {
                window.addEventListener('load', snapshotWhenSettled);
            }
        })();

        (function () {
            const form = () => document.getElementById('projektfilter-form');
            let savedSnapshot = null;

            const serializeForm = (f) => f ? new URLSearchParams(new FormData(f)).toString() : null;
            const snapshot = () => { savedSnapshot = serializeForm(form()); };

            window.projektfilterIsDirty = () => {
                const current = serializeForm(form());
                return current !== null && current !== savedSnapshot;
            };

            const snapshotWhenSettled = () => queueMicrotask(() => queueMicrotask(snapshot));

            window.addEventListener('open-modal', (event) => {
                if (event.detail === 'projektfilter') {
                    snapshotWhenSettled();
                }
            });

            if (document.readyState === 'complete') {
                snapshotWhenSettled();
            } else {
                window.addEventListener('load', snapshotWhenSettled);
            }
        })();
    </script>
</x-app-layout>
