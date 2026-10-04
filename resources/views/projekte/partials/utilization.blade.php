{{--
    Auslastung der Projektbeteiligten, nur mit den Stunden dieses Projekts (Ralf, 2026-10-04). Je Person ein
    Diagramm (links Arbeitszeit, rechts Grundlast + Projektstunden, Überbuchung rot) und darunter dieselben Zahlen
    wie in der Planungsseite. Die Diagramme zeichnet der Projekt-Tab nach dem Laden (canvas[data-chart]).
--}}
@php
    $isMonth = $data['mode'] === 'month';
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
    @endphp
    <section class="mb-5 rounded-lg border border-gray-200 bg-white p-3">
        <h4 class="mb-2 text-sm font-semibold text-gray-900">
            {{ $person->fullName() }}@if ($person->short_name) <span class="font-normal text-gray-400">({{ $person->short_name }})</span>@endif
        </h4>

        <div class="relative h-56">
            <canvas data-chart='@json($entry['chart'])' data-unit="{{ __('Std.') }}"></canvas>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500">
                        <th class="sticky left-0 z-[1] bg-white py-1 pr-2 text-left font-medium"></th>
                        @foreach ($data['periods'] as $period)
                            <th class="px-1 py-1 text-right font-medium tabular-nums" title="{{ $period['sub'] }}">{{ $period['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($metricLabels as $metric => $metricLabel)
                        <tr class="border-b border-gray-100 {{ in_array($metric, ['available', 'remaining'], true) ? 'font-semibold' : '' }}">
                            <td class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white py-1 pr-2 text-gray-600">{{ $metricLabel }}</td>
                            @foreach ($data['periods'] as $period)
                                @php
                                    $values = $rows[$period['key']];
                                    $value = (float) $values[$metric];
                                    $overloaded = in_array($metric, ['remaining', 'utilization'], true) && $values['remaining'] < -0.005;
                                @endphp
                                <td class="h-6 border-r border-gray-100 px-1 text-right tabular-nums {{ $overloaded ? 'bg-red-50 text-red-700' : '' }}">{{ $metric === 'utilization' ? number_format($value, 0, ',', '.').' %' : number_format($value, 2, ',', '.') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <p class="rounded-md border border-dashed border-gray-200 p-4 text-sm text-gray-400">{{ __('Diesem Projekt sind keine Personen zugeordnet.') }}</p>
@endforelse
