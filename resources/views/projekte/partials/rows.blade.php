{{--
    Zeilen der Projektübersicht - eigenes Partial, von index() (erster
    Batch) UND more() (Nachladen beim Scrollen) genutzt. Erwartet dieselben
    Variablen wie bisher inline in index.blade.php: $projects, $columns,
    $sort, $direction, $filters, $favoriteProjectIds, $directoryStatuses,
    $graphicOrderSummaries.
--}}
@forelse ($projects as $project)
    <tr
        class="{{ $project->verbund_rolle === 2 ? 'bg-gray-50 hover:bg-gray-100' : 'hover:bg-gray-50' }}"
        data-project-id="{{ $project->id }}"
        data-project-pn="{{ $project->source_pn }}"
        data-project-title="{{ $project->title }}"
        data-project-start="{{ $project->start_date?->format('Y-m-d') ?? '' }}"
        data-project-end="{{ $project->end_date?->format('Y-m-d') ?? '' }}"
        @if ($project->verbund_rolle === 1)
            x-data="{ verbundExpanded: true }"
        @elseif ($project->verbund_rolle === 2)
            x-data="{ verbundExpanded: true }"
            x-on:verbund-toggle-{{ $project->hauptprojekt_id }}.window="verbundExpanded = $event.detail"
            x-show="verbundExpanded"
        @endif
    >
        {{-- x-cloak nur, wenn die Spalte nicht schon von Anfang an sichtbar
             sein soll (reopen_group) - siehe gleiche Begründung beim <th>
             in projekte/index.blade.php. --}}
        <td
            x-show="$store.projectGrouping.active"
            @unless (request()->filled('reopen_group')) x-cloak @endunless
            class="px-2 py-2"
        >
            <input
                type="checkbox"
                class="rounded border-gray-300"
                :checked="$store.projectGrouping.memberIds.includes({{ $project->id }})"
                :disabled="! $store.projectGrouping.groupId"
                @change="$store.projectGrouping.toggleProject({{ $project->id }}, $event.target.checked)"
            >
        </td>
        <td class="{{ $project->verbund_rolle === 2 ? 'pl-12 pr-4' : 'px-4' }} py-2 whitespace-nowrap text-gray-500">
            <span class="inline-flex items-center gap-1">
                @if ($project->verbund_rolle === 1)
                    <button
                        type="button"
                        @click="verbundExpanded = ! verbundExpanded; window.dispatchEvent(new CustomEvent('verbund-toggle-{{ $project->id }}', { detail: verbundExpanded }))"
                        class="shrink-0 text-gray-400 hover:text-gray-600"
                        :title="verbundExpanded ? {{ \Illuminate\Support\Js::from(__('Unterprojekte einklappen')) }} : {{ \Illuminate\Support\Js::from(__('Unterprojekte ausklappen')) }}"
                    >
                        <svg class="h-3 w-3 transition-transform" :class="{ '-rotate-90': ! verbundExpanded }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                @endif
                <x-hauptprojekt-icon :project="$project" />
                <x-unterprojekt-icon :project="$project" />
                <x-pn-link :project="$project" :sort="$sort" :direction="$direction" :filters="$filters" />
                @if (in_array($project->id, $favoriteProjectIds, true))
                    <x-favorite-star :project="$project" :is-favorite="true" size="h-3.5 w-3.5" class="shrink-0" />
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
                @elseif ($column['key'] === 'archived')
                    @if ($project->archived)
                        {{ __('Ja') }}
                    @else
                        <span class="text-gray-400">&ndash;</span>
                    @endif
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
                        {{-- Ralf, 2026-09-19: Spalte breitenbegrenzen, viele Modelle dürfen umbrechen. --}}
                        <div class="col-cell-limit">{{ $project->products->pluck('name')->implode(', ') }}</div>
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
                @elseif ($column['key'] === 'project_groups')
                    @if ($project->projectGroups->isEmpty())
                        <span class="text-gray-400">&ndash;</span>
                    @else
                        {{ $project->projectGroups->pluck('name')->implode(', ') }}
                    @endif
                @elseif ($column['progress'] ?? false)
                    {{--
                        Ralf, 2026-09-14: "Datumsfortschritt parallel zum
                        Projektfortschritt in einer Spalte" - genau Viettos
                        "datbalken"-Spalte (ajax_ueb_getpncontent.php):
                        Datumsfortschritt (Zeitanteil Start->Ende, orange)
                        oben, Projektfortschritt (WFS-Erledigung, blau/grün
                        bei 100%) darunter - beide mit Tooltipp. Bei
                        verworfenen Projekten (status 3) wie in Vietto keine
                        Balken, nur "-".
                    --}}
                    @if ($project->status === 3)
                        <span class="text-gray-400">&ndash;</span>
                    @else
                        @php $dateProgress = $project->dateProgressPercent(); $progress = $project->progressPercent(); @endphp
                        <div class="space-y-1">
                            <div
                                class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200"
                                title="{{ $dateProgress !== null ? __('Datumsfortschritt: :percent %', ['percent' => $dateProgress]) : __('Datumsfortschritt: Start/Ende fehlt') }}"
                            >
                                <div class="h-full rounded-full bg-amber-400" style="width: {{ $dateProgress ?? 0 }}%"></div>
                            </div>
                            @if ($progress !== null)
                                <div class="flex items-center gap-1.5" title="{{ __('Projektfortschritt: :percent %', ['percent' => $progress]) }}">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full rounded-full {{ $progress >= 100 ? 'bg-green-500' : 'bg-blue-500' }}" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <span class="text-xs">{{ $progress }}%</span>
                                </div>
                            @else
                                <span class="text-gray-400" title="{{ __('Projektfortschritt: kein aktueller Workflow-Schritt') }}">&ndash;</span>
                            @endif
                        </div>
                    @endif
                @elseif ($column['start_end'] ?? false)
                    <div>{{ $project->start_date?->format('d.m.Y') ?? '–' }}</div>
                    <div class="text-gray-400">{{ $project->end_date?->format('d.m.Y') ?? '–' }}</div>
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
                @elseif ($column['key'] === 'stamm_id')
                    {{-- Ralf, 2026-09-20: "viel zu groß im Vergleich zu den anderen ...
                         ist ja eigentlich nicht so wichtig" - klein, grau, Festbreitenschrift. --}}
                    <span class="whitespace-nowrap font-mono text-[10px] text-gray-400">{{ $project->columnValue('stamm_id') }}</span>
                @elseif ($column['boolean'] ?? false)
                    @php $value = $project->columnValue($column['key']); @endphp
                    @if ($value === null)
                        <span class="text-gray-400">&ndash;</span>
                    @else
                        {{ $value ? __('Ja') : __('Nein') }}
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
                    @elseif ($value === null || $value === '')
                        <span class="text-gray-400">&ndash;</span>
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
