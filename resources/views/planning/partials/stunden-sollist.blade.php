{{--
    Planung > Stunden > Soll-Ist (Ralf, 2026-10-09): Ausblick ab Stichtag - wie viel der noch verfügbaren Projektstunden ist schon verplant, wie viel
    bleibt frei, als Summe und je Monat. Vergangenes zählt nicht (Rückblick Plan gegen Ist kommt später). Daten: App\Services\PlanningTargetActual. Erwartet $targetActual, $year, $fmt, $projectHoursTotal.
--}}
@php
    $cutoffLabel = $cutoff->format('d.m.Y');
    $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez'];
    $months = $targetActual['months'] ?? [];
    $highest = max(1.0, (float) collect($months)->max(fn ($m) => max($m['available'], $m['planned'])));
    $scale = (function (float $highest) {
        $step = 10 ** floor(log10($highest / 5));
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            if ($step * $factor * 5 >= $highest) {
                $step *= $factor;

                break;
            }
        }

        return ['max' => ceil($highest / $step) * $step, 'ticks' => range(0, (int) round(ceil($highest / $step) * $step / $step))];
    })($highest);
    $axisMax = (float) $scale['max'];
    $stepSize = $axisMax / max(1, count($scale['ticks']) - 1);
    $pct = fn (float $hours) => max(0, min(100, $hours / $axisMax * 100));
    $overbooked = ($targetActual['remaining'] ?? 0) < 0;
@endphp
<div id="planning-stunden-content" class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white p-4">
    @if ($cutoffPast)
        <p class="py-8 text-center text-sm text-gray-400">{{ __('Das Jahr :year liegt vor dem gewählten Stichtag. Die Soll-Ist-Ansicht blickt nur nach vorn; ein Rückblick auf geplante und tatsächliche Stunden folgt später.', ['year' => $year]) }}</p>
    @elseif ($targetActual['available'] <= 0 && $targetActual['planned'] <= 0)
        <p class="py-8 text-center text-sm text-gray-400">{{ __('Ab :date gibt es keine verfügbaren Projektstunden und keine verplanten Stunden.', ['date' => $cutoffLabel]) }}</p>
    @else
        <p class="mb-3 text-xs text-gray-600">{{ __('Ausblick ab :date', ['date' => $cutoffLabel]) }} · {{ __('Gesamtes Jahr: :hours Std. verfügbar', ['hours' => $fmt($targetActual['yearAvailable'])]) }}</p>
        {{-- Gesamtsumme --}}
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md border border-gray-200 p-3" title="{{ __('Arbeitszeit minus Abwesenheiten aus dem Kalender minus Grundlast, tageweise über das Jahr gerechnet. Weicht von den „Projektstd.“ der Tabelle ab, weil dort der Urlaubsanspruch abgezogen wird, hier die eingetragenen Abwesenheiten.') }}">
                <div class="text-xs text-gray-500">{{ __('Verfügbar für Projekte') }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-gray-800">{{ $fmt($targetActual['available']) }} <span class="text-sm font-normal text-gray-400">{{ __('Std.') }}</span></div>
            </div>
            <div class="rounded-md border border-gray-200 p-3" title="{{ __('Planstunden, die in Projekten (Geplant, In Bearbeitung) auf Personen verteilt sind, über Projektlaufzeit und Einsatzplan auf die Tage gelegt.') }}">
                <div class="text-xs text-gray-500">{{ __('Verplant') }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-sky-700">{{ $fmt($targetActual['planned']) }} <span class="text-sm font-normal text-gray-400">{{ __('Std.') }}</span></div>
            </div>
            <div class="rounded-md border p-3 {{ $overbooked ? 'border-red-300 bg-red-50' : 'border-gray-200' }}">
                <div class="text-xs text-gray-500">{{ $overbooked ? __('Überplant') : __('Noch übrig') }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums {{ $overbooked ? 'text-red-700' : 'text-gray-800' }}">{{ $fmt(abs($targetActual['remaining'])) }} <span class="text-sm font-normal text-gray-400">{{ __('Std.') }}</span></div>
            </div>
            <div class="rounded-md border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('Auslastung') }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-gray-800">{{ $targetActual['utilization'] !== null ? number_format($targetActual['utilization'], 1, ',', '.').' %' : '–' }}</div>
            </div>
        </div>
        @if ($targetActual['unassigned'] > 0)
            <p class="mb-6 text-xs text-gray-600">
                {{ __('Noch nicht auf Personen verteilt: :hours Std. in :n Projekten. Sie zählen hier nicht als verplant.', ['hours' => $fmt($targetActual['unassigned']), 'n' => $targetActual['unassignedProjects']]) }}
            </p>
        @endif

        {{-- Monate --}}
        <div class="mb-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-600">
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-sky-300"></span>{{ __('Verplant') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-gray-200"></span>{{ __('Noch übrig') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-sm bg-red-400"></span>{{ __('Überplant') }}</span>
        </div>
        <div class="flex">
            <div class="relative mr-2 h-[18rem] w-14 shrink-0 text-right text-[11px] text-gray-400">
                @foreach ($scale['ticks'] as $i)
                    <span class="absolute right-0 -translate-y-1/2 tabular-nums" style="bottom: {{ $pct($i * $stepSize) }}%">{{ number_format($i * $stepSize, 0, ',', '.') }}</span>
                @endforeach
                <span class="absolute right-0 top-full mt-1 text-[11px] text-gray-400">{{ __('Std.') }}</span>
            </div>
            <div class="min-w-0 flex-1 overflow-x-auto pb-2">
                <div class="relative" style="min-width: 40rem">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-[18rem]">
                        @foreach ($scale['ticks'] as $i)
                            @if ($i == 0)
                                <div class="absolute inset-x-0 border-t-2 border-gray-500/70" style="bottom: calc(0% - 1px)"></div>
                            @else
                                <div class="absolute inset-x-0 border-t border-gray-400" style="bottom: {{ $pct($i * $stepSize) }}%"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="relative grid h-[18rem] grid-cols-12 items-end gap-3 pt-2">
                        @foreach ($months as $m)
                            @php
                                $avail = max(0.0, (float) $m['available']);
                                $plan = max(0.0, (float) $m['planned']);
                                $over = max(0.0, $plan - $avail);
                                $planWithin = min($plan, $avail);
                                $left = max(0.0, $avail - $plan);
                                $total = max($avail, $plan);
                                $tip = $monthNames[$m['month']].' '.$year."\n".__('Verfügbar').': '.$fmt($avail)."\n".__('Verplant').': '.$fmt($plan)."\n"
                                    .($m['remaining'] < 0 ? __('Überplant').': '.$fmt(abs($m['remaining'])) : __('Noch übrig').': '.$fmt($m['remaining']))
                                    .($avail > 0 ? "\n".__('Auslastung').': '.round($plan / $avail * 100).' %' : '');
                            @endphp
                            <div class="relative flex h-full flex-col justify-end" title="{{ $m['past'] ? $monthNames[$m['month']].' '.$year."
".__('Liegt vor dem Stichtag') : $tip }}">
                                @if (! $m['past'])
                                <div class="relative mx-auto flex w-full max-w-[3.5rem] flex-col justify-end" style="height: {{ $pct($total) }}%">
                                    <span class="absolute bottom-full left-1/2 mb-1 -translate-x-1/2 whitespace-nowrap text-[10px] tabular-nums text-gray-500">{{ number_format($m['remaining'], 0, ',', '.') }}</span>
                                    @if ($over > 0)<div class="rounded-t bg-red-400" style="height: {{ $total > 0 ? $over / $total * 100 : 0 }}%"></div>@endif
                                    @if ($left > 0)<div class="{{ $planWithin > 0 ? '' : 'rounded-b' }} rounded-t bg-gray-200" style="height: {{ $total > 0 ? $left / $total * 100 : 0 }}%"></div>@endif
                                    @if ($planWithin > 0)<div class="flex items-center justify-center rounded-b {{ $left > 0 || $over > 0 ? '' : 'rounded-t' }} bg-sky-300 text-[10px] tabular-nums text-sky-950" style="height: {{ $total > 0 ? $planWithin / $total * 100 : 0 }}%">@if ($pct($planWithin) >= 5){{ number_format($planWithin, 0, ',', '.') }}@endif</div>@endif
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1 grid grid-cols-12 gap-3">
                        @foreach ($months as $m)
                            <span class="text-center text-[11px] {{ $m['past'] ? 'text-gray-300' : 'text-gray-600' }}">{{ $monthNames[$m['month']] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-400">{{ __('Die Zahl über dem Balken ist die Restkapazität des Monats; negativ bedeutet überplant.') }}</p>

        {{-- Zahlen --}}
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-500">
                    <tr class="border-b border-gray-200">
                        <th class="px-3 py-2 text-left font-medium">{{ __('Monat') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Verfügbar') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Verplant') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Noch übrig') }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ __('Auslastung') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($months as $m)
                        @if ($m['past'])
                        <tr class="border-b border-gray-100 text-gray-300" title="{{ __('Liegt vor dem Stichtag') }}">
                            <td class="px-3 py-1.5">{{ $monthNames[$m['month']] }}</td>
                            <td class="px-3 py-1.5 text-right">–</td><td class="px-3 py-1.5 text-right">–</td><td class="px-3 py-1.5 text-right">–</td><td class="px-3 py-1.5 text-right">–</td>
                        </tr>
                        @continue
                        @endif
                        <tr class="border-b border-gray-100">
                            <td class="px-3 py-1.5">{{ $monthNames[$m['month']] }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($m['available']) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($m['planned']) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums {{ $m['remaining'] < 0 ? 'font-medium text-red-700' : '' }}">{{ $fmt($m['remaining']) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-600">{{ $m['available'] > 0 ? number_format($m['planned'] / $m['available'] * 100, 1, ',', '.').' %' : '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr class="border-t-2 border-gray-500">
                        <td class="px-3 py-2">{{ __('Summe ab Stichtag') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($targetActual['available']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($targetActual['planned']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums {{ $overbooked ? 'text-red-700' : '' }}">{{ $fmt($targetActual['remaining']) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $targetActual['utilization'] !== null ? number_format($targetActual['utilization'], 1, ',', '.').' %' : '–' }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
