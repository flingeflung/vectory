{{--
    Zeiten-Tab, Unterreiter "Nach Person & Tag" (Ralf, 2026-09-28) - Personen×Tage-
    Aufschlüsselung je Projekt, eigenes Recht project.hours.person_breakdown (siehe
    zeiten-body.blade.php für die Sichtbarkeits-Begründung). Eigene, per fetch()
    wechselbare Woche (gleiches Grundmuster wie das klassische Wochenraster,
    resources/views/jobload/index.blade.php) - Wochennavigation tauscht nur diesen
    Block per outerHTML aus, nicht den ganzen Zeiten-Tab.

    Am Hauptprojekt zusätzlich nach Unterprojekt aufgeschlüsselt (Ralf: "+ UP beim
    HP") - dieselbe Person kann dann mehrfach auftauchen, je beteiligtem Projekt.
--}}
@php
    $weekValue = sprintf('%04d-W%02d', $week->isoWeekYear(), $week->isoWeek());
    $prevWeekValue = sprintf('%04d-W%02d', $week->subWeek()->isoWeekYear(), $week->subWeek()->isoWeek());
    $nextWeekValue = sprintf('%04d-W%02d', $week->addWeek()->isoWeekYear(), $week->addWeek()->isoWeek());
    $fmt = fn ($hours) => number_format($hours, 2, ',', '.');
    $colspan = 2 + $breakdown['days']->count() + ($breakdown['isHauptprojekt'] ? 1 : 0);
@endphp
<div id="project-zeiten-personen-body">
    <div
        class="mb-2 flex flex-wrap items-center gap-2 text-xs"
        x-data="{
            async changeWeek(value) {
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.zeiten.personen', $project)) }} + '?week=' + value, {
                    headers: { 'Accept': 'text/html' },
                });
                document.getElementById('project-zeiten-personen-body').outerHTML = await response.text();
            },
        }"
    >
        <button type="button" @click="changeWeek({{ \Illuminate\Support\Js::from($prevWeekValue) }})" class="rounded border border-gray-300 px-2 py-0.5 hover:bg-gray-50" aria-label="{{ __('Vorherige Woche') }}">←</button>
        <input type="week" value="{{ $weekValue }}" @change="changeWeek($event.target.value)" class="rounded-md border-gray-300 py-0.5 text-xs">
        <button type="button" @click="changeWeek({{ \Illuminate\Support\Js::from($nextWeekValue) }})" class="rounded border border-gray-300 px-2 py-0.5 hover:bg-gray-50" aria-label="{{ __('Nächste Woche') }}">→</button>
        <span class="text-gray-500">{{ $week->format('d.m.Y') }} – {{ $week->addDays(6)->format('d.m.Y') }}</span>
    </div>

    <table class="w-full max-w-2xl text-xs">
        <thead>
            <tr class="border-b border-gray-200 text-gray-500">
                <th class="py-1 pr-3 text-left font-medium">{{ __('Person') }}</th>
                @if ($breakdown['isHauptprojekt'])
                    <th class="px-2 py-1 text-left font-medium">{{ __('Projekt') }}</th>
                @endif
                @foreach ($breakdown['days'] as $day)
                    <th class="px-1 py-1 text-center font-medium">{{ ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][$loop->index] }} {{ $day->format('d.m.') }}</th>
                @endforeach
                <th class="py-1 pl-3 text-right font-medium">{{ __('Summe') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($breakdown['rows'] as $row)
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3">{{ $row['name'] }}</td>
                    @if ($breakdown['isHauptprojekt'])
                        <td class="px-2 py-1 text-gray-500">{{ $row['projectLabel'] }}</td>
                    @endif
                    @foreach ($breakdown['days'] as $day)
                        <td class="px-1 py-1 text-center tabular-nums">{{ isset($row['days'][$day->toDateString()]) ? $fmt($row['days'][$day->toDateString()]) : '–' }}</td>
                    @endforeach
                    <td class="py-1 pl-3 text-right font-medium tabular-nums">{{ $fmt($row['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $colspan }}" class="py-4 text-center text-gray-400">{{ __('Keine Stunden in dieser Woche gebucht.') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-semibold text-gray-800">
                <td class="py-1 pr-3">{{ __('Summe') }}</td>
                @if ($breakdown['isHauptprojekt'])
                    <td></td>
                @endif
                @foreach ($breakdown['days'] as $day)
                    <td class="px-1 py-1 text-center tabular-nums">{{ $fmt($breakdown['dayTotals'][$day->toDateString()] ?? 0) }}</td>
                @endforeach
                <td class="py-1 pl-3 text-right tabular-nums">{{ $fmt($breakdown['total']) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
