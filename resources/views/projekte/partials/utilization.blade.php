{{--
    Auslastung der Projektbeteiligten, nur mit den Stunden dieses Projekts (Ralf, 2026-10-04). Je Person ein
    Diagramm (Säulen: Grundlast + Projektstunden, Überbuchung rot; Linie: verfügbare Arbeitszeit) und darunter dieselben
    Zahlen wie in der Planungsseite. Kopfzeile, Diagramm und Tabelle teilen sich dieselbe Spaltenbreite, damit die Tage
    senkrecht untereinander stehen. Die Diagramme zeichnet der Projekt-Tab nach dem Laden (canvas[data-chart]).
--}}
@php
    $isMonth = $data['mode'] === 'month';
    $labelWidth = 170;
    $columnWidth = $isMonth ? 34 : 40;
    $periodCount = $data['periods']->count();
    $totalWidth = $labelWidth + $periodCount * $columnWidth;
    $cellClass = fn (array $period) => $period['holiday'] ? 'bg-violet-50' : ($period['weekend'] ? 'bg-gray-100' : '');
@endphp

@if ($data['members']->count() > 1)
    <p class="mb-2 text-xs text-gray-500">
        {{ __('Kumuliert für das Hauptprojekt und seine Unterprojekte:') }}
        {{ $data['members']->map(fn ($member) => $member->source_pn)->join(', ') }}
    </p>
@endif

<div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
    <span><span class="mr-1 inline-block h-0.5 w-5 bg-green-600 align-middle"></span>{{ __('Arbeitszeit (verfügbar)') }}</span>
    @if ($isMonth)
        <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-gray-200 align-middle"></span>{{ __('Wochenende') }}</span>
        <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-violet-200 align-middle"></span>{{ __('Feiertag') }}</span>
        <span><span class="mr-1 inline-block h-2.5 w-2.5 rounded-sm bg-amber-200 align-middle"></span>{{ __('Abwesenheit') }}</span>
    @endif
</div>

@forelse ($data['people'] as $entry)
    @php
        $person = $entry['person'];
        $rows = $entry['rows'];
        $absenceFlags = $entry['chart']['flags'];
    @endphp
    <section class="mb-5 rounded-lg border border-gray-200 bg-white p-3">
        <h4 class="mb-2 text-sm font-semibold text-gray-900">
            {{ $person->fullName() }}@if ($person->short_name) <span class="font-normal text-gray-400">({{ $person->short_name }})</span>@endif
        </h4>

        <div class="overflow-x-auto">
            <div style="width: {{ $totalWidth }}px">
                {{-- Kopfzeile: Tag bzw. Woche, Wochentag, Wochenende und Feiertag hervorgehoben --}}
                <div class="grid text-center text-[11px] leading-tight text-gray-500" style="grid-template-columns: {{ $labelWidth }}px repeat({{ $periodCount }}, {{ $columnWidth }}px)">
                    <div></div>
                    @foreach ($data['periods'] as $period)
                        @php $flag = $absenceFlags[$loop->index] ?? ''; @endphp
                        <div class="border-b border-gray-200 py-0.5 {{ $cellClass($period) }} {{ $flag === 'absence' ? 'bg-amber-50' : '' }}" title="{{ $period['holiday'] ?? $period['sub'] }}">
                            <div>{{ $period['sub'] }}</div>
                            <div class="font-semibold tabular-nums text-gray-700">{{ $period['label'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="relative h-56" style="width: {{ $totalWidth }}px">
                    <canvas data-chart='@json($entry['chart'])' data-unit="{{ __('Std.') }}" data-label-width="{{ $labelWidth }}"></canvas>
                </div>

                <table class="text-xs" style="table-layout: fixed; width: {{ $totalWidth }}px">
                    <colgroup>
                        <col style="width: {{ $labelWidth }}px">
                        @foreach ($data['periods'] as $period)
                            <col style="width: {{ $columnWidth }}px">
                        @endforeach
                    </colgroup>
                    <tbody>
                        @foreach ($metricLabels as $metric => $metricLabel)
                            <tr class="border-b border-gray-100 {{ in_array($metric, ['available', 'remaining'], true) ? 'font-semibold' : '' }}">
                                <td class="truncate border-r border-gray-200 py-1 pr-2 text-gray-600" title="{{ $metricLabel }}">{{ $metricLabel }}</td>
                                @foreach ($data['periods'] as $period)
                                    @php
                                        $values = $rows[$period['key']];
                                        $value = (float) $values[$metric];
                                        $overloaded = in_array($metric, ['remaining', 'utilization'], true) && $values['remaining'] < -0.005;
                                        $flag = $absenceFlags[$loop->index] ?? '';
                                    @endphp
                                    <td class="h-6 border-r border-gray-100 px-0.5 text-right tabular-nums {{ $overloaded ? 'bg-red-50 text-red-700' : ($flag === 'absence' ? 'bg-amber-50' : $cellClass($period)) }}">{{ $metric === 'utilization' ? number_format($value, 0, ',', '.').'%' : number_format($value, 2, ',', '.') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@empty
    <p class="rounded-md border border-dashed border-gray-200 p-4 text-sm text-gray-400">{{ __('Diesem Projekt sind keine Personen zugeordnet.') }}</p>
@endforelse
