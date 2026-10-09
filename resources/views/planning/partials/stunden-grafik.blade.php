{{--
    Planung > Stunden > Grafik (Ralf, 2026-10-09): ein Balken je Person. Die Gesamthöhe entspricht den Jahresstunden, unten die Grundlast,
    darüber die Projektstunden (Jahresstunden minus Grundlast = für Projekte verfügbar). Reine HTML/CSS-Balken, Tooltips wie überall.
    Erwartet $rows, $chart (max, ticks), $sort, $direction, $year, $fmt sowie $total, $baseLoadTotal, $projectHoursTotal.
--}}
@php
    $max = $chart['max'];
    $pct = fn (float $hours) => $max > 0 ? max(0, min(100, $hours / $max * 100)) : 0;
    $sortLabels = ['name' => __('Name'), 'annual_hours' => __('Jahresstunden'), 'base_load' => __('Grundlast'), 'project_hours' => __('Projektstunden')];
    $activeSort = array_key_exists($sort, $sortLabels) ? $sort : 'name';
@endphp
<div id="planning-stunden-content" class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white p-4">
    @if ($rows->isEmpty())
        <p class="py-8 text-center text-sm text-gray-400">{{ __('Für :year sind keine Personen sichtbar - entweder ist bei niemandem "Ressourcenplanung" angehakt, oder es fehlen gültige Wochenstunden-Daten für dieses Jahr.', ['year' => $year]) }}</p>
    @else
        <div class="mb-10 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-600">
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-slate-400"></span>{{ __('Grundlast') }}: <b class="tabular-nums text-gray-800">{{ $fmt($baseLoadTotal) }}</b></span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-sky-300"></span>{{ __('Projektstunden') }}: <b class="tabular-nums text-gray-800">{{ $fmt($projectHoursTotal) }}</b></span>
            <span>{{ __('Jahresstunden gesamt') }}: <b class="tabular-nums text-gray-800">{{ $fmt($total) }}</b></span>
            <span class="flex-1"></span>
            <span class="inline-flex items-center gap-2">
                {{ __('Sortierung') }}:
                @foreach ($sortLabels as $key => $label)
                    @php
                        $nextDirection = $activeSort === $key && $direction === 'asc' ? 'desc' : 'asc';
                    @endphp
                    <a href="{{ request()->url().'?'.http_build_query(array_merge(request()->query(), ['sort' => $key, 'direction' => $nextDirection])) }}"
                       class="{{ $activeSort === $key ? 'font-semibold text-indigo-700' : 'text-gray-500 hover:text-gray-800' }}">{{ $label }}@if ($activeSort === $key) {{ $direction === 'asc' ? '▲' : '▼' }}@endif</a>
                @endforeach
            </span>
        </div>

        <div class="flex">
            {{-- Achse --}}
            <div class="relative mr-2 h-[22rem] w-14 shrink-0 text-right text-[11px] text-gray-400">
                @foreach ($chart['ticks'] as $tick)
                    <span class="absolute right-0 -translate-y-1/2 tabular-nums" style="bottom: {{ $pct($tick) }}%">{{ number_format($tick, 0, ',', '.') }}</span>
                @endforeach
                <span class="absolute -top-8 right-0 text-[10px]">{{ __('Std.') }}</span>
            </div>

            <div class="min-w-0 flex-1 overflow-x-auto pb-2">
                <div class="relative" style="min-width: {{ max(1, $rows->count()) * 3.75 }}rem">
                    {{-- Hilfslinien --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-[22rem]">
                        @foreach ($chart['ticks'] as $tick)
                            <div class="absolute inset-x-0 border-t-2 border-gray-500/70" style="bottom: calc({{ $pct($tick) }}% - 1px)"></div>
                        @endforeach
                    </div>

                    {{-- Balken --}}
                    <div class="relative flex h-[22rem] items-end gap-3 pt-2">
                        @foreach ($rows as $row)
                            @php
                                $annual = max(0.0, (float) $row['jahresstd']);
                                $base = max(0.0, (float) $row['baseLoad']);
                                $project = (float) $row['projectHours'];
                                $overloaded = $project < 0;
                                $baseShown = $overloaded ? $annual : min($base, $annual);
                                $projectShown = $overloaded ? 0.0 : max(0.0, $project);
                                $tip = $row['lastName'].', '.$row['firstName'].($row['inactive'] ? ' ['.__('Inaktiv').']' : '')."\n"
                                    .__('Jahresstunden').': '.$fmt($annual)."\n"
                                    .__('Grundlast').': '.$fmt($base).($annual > 0 ? ' ('.round($base / $annual * 100).' %)' : '')."\n"
                                    .__('Projektstunden').': '.$fmt($project).($annual > 0 && ! $overloaded ? ' ('.round($project / $annual * 100).' %)' : '')
                                    .($overloaded ? "\n".__('Die Grundlast übersteigt die Jahresstunden.') : '');
                            @endphp
                            <div class="relative flex h-full w-12 shrink-0 flex-col justify-end {{ $row['inactive'] ? 'opacity-60' : '' }}" title="{{ $tip }}">
                                <div class="relative flex flex-col justify-end" style="height: {{ $pct($annual) }}%">
                                    <span class="absolute bottom-full left-1/2 mb-1 -translate-x-1/2 whitespace-nowrap text-[10px] tabular-nums text-gray-500">{{ number_format($annual, 0, ',', '.') }}</span>
                                    @if ($projectShown > 0)
                                        <div class="flex items-center justify-center rounded-t bg-sky-300 text-[10px] tabular-nums text-sky-950" style="height: {{ $annual > 0 ? $projectShown / $annual * 100 : 0 }}%">
                                            @if ($pct($projectShown) >= 5){{ number_format($projectShown, 0, ',', '.') }}@endif
                                        </div>
                                    @endif
                                    <div class="flex items-center justify-center {{ $projectShown > 0 ? '' : 'rounded-t' }} {{ $overloaded ? 'bg-red-400' : 'bg-slate-400' }} text-[10px] tabular-nums text-white" style="height: {{ $annual > 0 ? $baseShown / $annual * 100 : 0 }}%">
                                        @if ($pct($baseShown) >= 5){{ number_format($baseShown, 0, ',', '.') }}@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Namen --}}
                    <div class="mt-1 flex gap-3">
                        @foreach ($rows as $row)
                            <button
                                type="button"
                                class="w-12 shrink-0 truncate text-center text-[11px] text-gray-600 hover:text-indigo-700 {{ $row['inactive'] ? 'opacity-60' : '' }}"
                                title="{{ $row['lastName'] }}, {{ $row['firstName'] }}"
                                onclick="window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $row['personId'] }} } }))"
                            >{{ $row['shortName'] !== '' ? $row['shortName'] : $row['lastName'] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-400">{{ __('Die Balkenhöhe entspricht den Jahresstunden der Person (nach Abzug von Feiertagen und Urlaub). Unten die Grundlast, darüber die für Projekte verfügbaren Stunden.') }}</p>
    @endif
</div>
