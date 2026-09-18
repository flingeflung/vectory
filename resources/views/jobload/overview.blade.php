<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Übersicht') }}</h2>
    </x-slot>

    <div class="flex h-full flex-col gap-2 p-2 sm:p-3">
        <form method="GET" action="{{ route('jobload.overview') }}" class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <fieldset class="flex items-center gap-3">
                <legend class="sr-only">{{ __('Darstellung') }}</legend>
                <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="person" onchange="window.submitJobloadOverview(this.form)" @checked($mode === 'person') class="border-gray-300 text-btn-primary">{{ __('Nach Personen') }}</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="job" onchange="window.submitJobloadOverview(this.form)" @checked($mode === 'job') class="border-gray-300 text-btn-primary">{{ __('Nach Themen') }}</label>
            </fieldset>
            <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" onchange="window.submitJobloadOverview(this.form)" class="w-24 rounded-md border-gray-300 py-1 text-sm">
            </label>
            @if ($mode === 'person')
                <label class="flex items-center gap-2 text-gray-700">{{ __('Person') }}
                    <select name="person_id" onchange="window.submitJobloadOverview(this.form)" class="max-w-72 rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected($personId === $person->id)>{{ $person->last_name }}, {{ $person->first_name }}{{ ! $person->active ? ' [i]' : '' }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <label class="flex items-center gap-2 text-gray-700">{{ __('Thema') }}
                    <select name="job_id" onchange="window.submitJobloadOverview(this.form)" class="max-w-80 rounded-md border-gray-300 py-1 text-sm">
                        <option value="" @selected($jobId === null)>{{ __('Alle Themen') }}</option>
                        @foreach ($jobs as $job)
                            <option value="{{ $job->id }}" @selected($jobId === $job->id)>{{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </form>

        <div id="jobload-overview-scroll" class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-max border-collapse text-xs">
                <thead class="sticky top-0 z-10 bg-gray-50 text-gray-700">
                    <tr>
                        <th colspan="2" class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($monthSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 px-1 py-0.5 text-center font-semibold">{{ $segment['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-44 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ $mode === 'job' && $jobId !== null ? __('Person') : __('Job') }}</th>
                        <th class="border-b border-r border-gray-200 px-2 py-1 text-right font-medium">{{ __('Ges.') }}</th>
                        @foreach ($weeks as $week)
                            <th class="min-w-16 border-b border-r border-gray-200 px-1 py-1 text-center font-medium {{ $week['key'] === $currentWeekKey ? 'bg-[#fffaeb]' : '' }}" title="{{ $week['start']->format('d.m.Y') }} – {{ $week['end']->format('d.m.Y') }}">
                                {{ __('KW') }} {{ $week['number'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $detailJobId = $mode === 'job' && $jobId !== null ? $jobId : $row['id'];
                            $detailPersonId = $mode === 'person' ? $personId : ($jobId !== null ? $row['id'] : null);
                        @endphp
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white px-2 py-1 text-left font-normal text-gray-800">{{ $row['label'] }}</th>
                            <td class="border-r border-gray-200 px-2 py-1 text-right font-semibold tabular-nums">{{ number_format($row['total'], $hourDecimals, ',', '.') }}</td>
                            @foreach ($weeks as $week)
                                @php
                                    $value = $row['weeks'][$week['key']] ?? 0;
                                @endphp
                                <td class="border-r border-gray-100 px-1 py-1 text-center tabular-nums {{ $week['key'] === $currentWeekKey ? 'bg-[#fffaeb]' : '' }}">
                                    @if ($value > 0)
                                        <button type="button" data-week="{{ $week['key'] }}" data-job-id="{{ $detailJobId }}" data-person-id="{{ $detailPersonId }}" onclick="window.openJobloadWeekDetail(this)" class="text-indigo-600 hover:underline" title="{{ __('Tageswerte anzeigen') }}">{{ number_format($value, $hourDecimals, ',', '.') }}</button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $weeks->count() + 2 }}" class="px-4 py-8 text-center text-gray-500">{{ __('Keine Stunden im gewählten Jahr erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr>
                        <th class="sticky left-0 z-[1] border-r border-gray-200 bg-gray-50 px-2 py-1 text-left">{{ __('Summe') }}</th>
                        <td class="border-r border-gray-200 px-2 py-1 text-right tabular-nums">{{ number_format($yearTotal, $hourDecimals, ',', '.') }}</td>
                        @foreach ($weeks as $week)
                            <td class="border-r border-gray-200 px-1 py-1 text-center tabular-nums {{ $week['key'] === $currentWeekKey ? 'bg-[#fffaeb]' : '' }}">{{ $weekTotals[$week['key']] > 0 ? number_format($weekTotals[$week['key']], $hourDecimals, ',', '.') : '' }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="shrink-0 text-xs text-gray-500">{{ trans_choice(':count Eintrag|:count Einträge', $rows->count(), ['count' => $rows->count()]) }} · {{ __('Jahressumme') }}: {{ number_format($yearTotal, $hourDecimals, ',', '.') }} {{ __('Stunden') }}</div>
    </div>

    <x-modal name="jobload-week-detail" max-width="xl" :draggable="true">
        <div class="flex max-h-[75vh] flex-col">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-100 px-4 py-2" data-drag-handle title="{{ __('Ziehen zum Verschieben') }}">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Wochenwerte') }}</h3>
                <button type="button" @click="$dispatch('close-modal', 'jobload-week-detail')" class="text-gray-500 hover:text-gray-700" aria-label="{{ __('Schließen') }}">×</button>
            </div>
            <div id="jobload-week-detail-body" class="min-h-0 overflow-auto p-3 text-sm text-gray-600"></div>
            <div class="flex justify-end border-t border-gray-200 px-4 py-3">
                <button type="button" x-data @click="$dispatch('close-modal', 'jobload-week-detail')" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Schließen') }}</button>
            </div>
        </div>
    </x-modal>
    <script>
        const jobloadScrollKey = 'jobload-overview-scroll:' + window.location.pathname;
        const jobloadScroll = document.getElementById('jobload-overview-scroll');
        try {
            const savedScroll = sessionStorage.getItem(jobloadScrollKey);
            if (savedScroll !== null) {
                sessionStorage.removeItem(jobloadScrollKey);
                requestAnimationFrame(() => { jobloadScroll.scrollLeft = Number(savedScroll) || 0; });
            }
        } catch (error) {
            // Die Übersicht bleibt auch ohne Web Storage nutzbar.
        }
        window.submitJobloadOverview = function (form) {
            try {
                sessionStorage.setItem(jobloadScrollKey, String(jobloadScroll.scrollLeft));
            } catch (error) {
                // Die Filter funktionieren auch ohne Web Storage.
            }
            form.submit();
        };
        window.openJobloadWeekDetail = async function (button) {
            const body = document.getElementById('jobload-week-detail-body');
            body.textContent = {{ \Illuminate\Support\Js::from(__('Lädt…')) }};
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'jobload-week-detail' }));
            const url = new URL({{ \Illuminate\Support\Js::from(route('jobload.overview.week-detail')) }}, window.location.origin);
            url.searchParams.set('week', button.dataset.week);
            url.searchParams.set('job_id', button.dataset.jobId);
            if (button.dataset.personId) url.searchParams.set('person_id', button.dataset.personId);
            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error('load failed');
                body.innerHTML = await response.text();
            } catch (error) {
                body.textContent = {{ \Illuminate\Support\Js::from(__('Wochenwerte konnten nicht geladen werden.')) }};
            }
        };
    </script>
</x-app-layout>
