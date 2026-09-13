{{--
    Zeilen der Projektübersicht - eigenes Partial, von index() (erster
    Batch) UND more() (Nachladen beim Scrollen) genutzt. Erwartet dieselben
    Variablen wie bisher inline in index.blade.php: $projects, $columns,
    $sort, $direction, $filters, $favoriteProjectIds, $directoryStatuses,
    $graphicOrderSummaries.
--}}
@forelse ($projects as $project)
    <tr class="hover:bg-gray-50">
        <td x-show="$store.projectGrouping.active" x-cloak class="px-2 py-2">
            <input
                type="checkbox"
                class="rounded border-gray-300"
                :checked="$store.projectGrouping.memberIds.includes({{ $project->id }})"
                :disabled="! $store.projectGrouping.groupId"
                @change="$store.projectGrouping.toggleProject({{ $project->id }}, $event.target.checked)"
            >
        </td>
        <td class="px-4 py-2 whitespace-nowrap text-gray-500">
            <span class="inline-flex items-center gap-1">
                <x-pn-link :project="$project" :sort="$sort" :direction="$direction" :filters="$filters" />
                @if (in_array($project->id, $favoriteProjectIds, true))
                    <x-favorite-star :project="$project" :is-favorite="true" size="h-3.5 w-3.5" />
                @endif
                <x-project-directory-status :project="$project" :status="$directoryStatuses[$project->id]" />
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
                @elseif ($column['key'] === 'status')
                    <x-status-icon :status="$project->status" />
                @elseif ($column['key'] === 'workflow')
                    @if ($project->workflow)
                        <div title="{{ $project->workflow->name }}">{{ $project->workflow->id }} - {{ $project->workflow->short_name }}</div>
                        @if ($project->progressStepLabel())
                            <div class="text-xs text-gray-400" title="{{ $project->currentStepTitle() }}">{{ $project->progressStepLabel() }}</div>
                        @endif
                    @else
                        <span class="text-gray-400" title="{{ __('– Kein Workflow zugewiesen –') }}">&ndash;</span>
                    @endif
                @elseif ($column['key'] === 'system_model')
                    @if ($project->products->isEmpty())
                        <span class="text-gray-400">&ndash;</span>
                    @else
                        {{ $project->products->pluck('name')->implode(', ') }}
                    @endif
                @elseif ($column['key'] === 'product_group_number')
                    @php $groupNumbers = $project->products->pluck('productGroup.number')->filter()->unique(); @endphp
                    @if ($groupNumbers->isEmpty())
                        <span class="text-gray-400">&ndash;</span>
                    @else
                        {{ $groupNumbers->implode(', ') }}
                    @endif
                @elseif ($column['key'] === 'product_group_name')
                    @php $groupNames = $project->products->pluck('productGroup.name')->filter()->unique(); @endphp
                    @if ($groupNames->isEmpty())
                        <span class="text-gray-400">&ndash;</span>
                    @else
                        {{ $groupNames->implode(', ') }}
                    @endif
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
