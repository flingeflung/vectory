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
    // Farben wie im Kalender: Feiertag #fdf2f8, Wochenende #fffaeb, heute #eff6ff
    $cellClass = fn (array $period) => $period['holiday'] ? 'bg-[#fdf2f8]' : ($period['weekend'] ? 'bg-[#fffaeb]' : '');
@endphp

@if ($data['members']->count() > 1)
    <p class="mb-2 text-xs text-gray-500">
        {{ __('Kumuliert für das Hauptprojekt und seine Unterprojekte:') }}
        {{ $data['members']->map(fn ($member) => $member->source_pn)->join(', ') }}
    </p>
@endif

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
                        <div class="py-0.5 {{ $period['today'] ? 'border-b-2 border-blue-600 bg-[#eff6ff] text-blue-800' : 'border-b border-gray-200 '.$cellClass($period).' '.($flag === 'absence' ? 'bg-amber-100' : '') }}" title="{{ $period['today'] ? __('Heute').($period['holiday'] ? ': '.$period['holiday'] : '') : ($period['holiday'] ?? $period['sub']) }}">
                            <div>{{ $period['sub'] }}</div>
                            <div class="font-semibold tabular-nums {{ $period['today'] ? 'text-blue-800' : 'text-gray-700' }}">{{ $period['label'] }}</div>
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
                                    <td class="h-6 border-r border-gray-100 px-0.5 text-right tabular-nums {{ $overloaded ? 'bg-red-50 text-red-700' : ($period['today'] ? 'bg-[#eff6ff]' : ($flag === 'absence' ? 'bg-amber-100' : $cellClass($period))) }}">{{ $metric === 'utilization' ? number_format($value, 0, ',', '.').'%' : number_format($value, 2, ',', '.') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Legende (außerhalb des Scrollbereichs, damit sie immer sichtbar bleibt); in der Jahresansicht links der Hinweis zu den Feiertagen --}}
        <div class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-gray-600">
            <span class="text-gray-400">
                @unless ($isMonth)
                    {{ __('In Wochen mit Feiertagen ist die Arbeitszeit entsprechend reduziert') }}
                @endunless
            </span>
            <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span><span class="mr-1.5 inline-block h-0.5 w-5 align-middle" style="background-color: #16a34a"></span>{{ $isMonth ? __('Arbeitszeit') : __('Wochenarbeitszeit') }}</span>
                <span><span class="mr-1.5 inline-block h-2.5 w-5 align-middle" style="background-color: #94a3b8"></span>{{ __('Grundlast') }}</span>
                <span><span class="mr-1.5 inline-block h-2.5 w-5 align-middle" style="background-color: #3b82f6"></span>{{ __('Projekt') }}</span>
                <span><span class="mr-1.5 inline-block h-2.5 w-5 align-middle" style="background-color: #ef4444"></span>{{ __('Überbuchung') }}</span>
                @if ($data['periods']->contains('today', true))
                    <span><span class="mr-1.5 inline-block h-2.5 w-3.5 rounded-sm border border-blue-300 bg-[#eff6ff] align-middle"></span>{{ $isMonth ? __('Heute') : __('Aktuelle Woche') }}</span>
                @endif
                @if ($isMonth)
                    <span><span class="mr-1.5 inline-block h-2.5 w-3.5 rounded-sm border border-gray-200 bg-[#fffaeb] align-middle"></span>{{ __('Wochenende') }}</span>
                    <span><span class="mr-1.5 inline-block h-2.5 w-3.5 rounded-sm border border-gray-200 bg-[#fdf2f8] align-middle"></span>{{ __('Feiertag') }}</span>
                    <span><span class="mr-1.5 inline-block h-2.5 w-3.5 rounded-sm bg-amber-100 align-middle"></span>{{ __('Abwesenheit') }}</span>
                @endif
            </span>
        </div>
    </section>
@empty
    <p class="rounded-md border border-dashed border-gray-200 p-4 text-sm text-gray-400">{{ __('Diesem Projekt sind keine Personen zugeordnet.') }}</p>
@endforelse
