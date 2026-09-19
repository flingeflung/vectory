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
                @if ($canViewAll)
                    <label class="flex items-center gap-1.5"><input type="radio" name="mode" value="group" onchange="window.submitJobloadOverview(this.form)" @checked($mode === 'group') class="border-gray-300 text-btn-primary">{{ __('Nach Jobgruppen') }}</label>
                @endif
            </fieldset>
            <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" onchange="window.submitJobloadOverview(this.form)" class="w-24 rounded-md border-gray-300 py-1 text-sm">
            </label>
            @if ($mode === 'person')
                <label class="flex items-center gap-2 text-gray-700">{{ __('Person') }}
                    <select name="person_id" onchange="window.submitJobloadOverview(this.form)" class="max-w-72 rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected($personId === $person->id) @class(['text-gray-400' => ! $person->active])>{{ $person->last_name }}, {{ $person->first_name }}{{ ! $person->active ? ' [i]' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($canViewAll)
                    <label class="flex items-center gap-1.5 text-gray-600">
                        <input type="checkbox" name="show_inactive" value="1" @checked($showInactive) onchange="window.submitJobloadOverview(this.form)" class="rounded border-gray-300">
                        {{ __('Inaktive zeigen') }}
                    </label>
                @endif
            @elseif ($mode === 'job')
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

        @if ($mode === 'group')
            @php
                $fmt = fn ($hours) => number_format($hours, $hourDecimals, ',', '.');
                $groupLink = fn ($id) => route('jobload.overview', array_filter(['mode' => 'group', 'year' => $year, 'group_id' => $id]));
                $evalTitle = $selectedGroup?->name ?? __('Alle Jobgruppen');
            @endphp
            <div class="flex min-h-0 flex-1 gap-4">
                <div class="flex w-64 shrink-0 flex-col rounded-lg border border-gray-200 bg-white">
                    <div class="shrink-0 border-b border-gray-100 p-2 text-xs font-semibold text-gray-500">{{ __('Jobgruppen') }}</div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-2 text-sm">
                        <a href="{{ $groupLink(null) }}" class="flex justify-between rounded px-2 py-1.5 {{ $selectedGroup === null ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <span>{{ __('– Alle –') }}</span>
                            <span class="text-xs text-gray-400 tabular-nums">{{ $fmt($hoursByGroup->sum()) }}</span>
                        </a>
                        @foreach ($groups as $group)
                            <a href="{{ $groupLink($group->id) }}" class="flex justify-between gap-2 rounded px-2 py-1.5 {{ $selectedGroup?->id === $group->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="truncate">{{ $group->name }}</span>
                                <span class="shrink-0 text-xs text-gray-400 tabular-nums">{{ $fmt($hoursByGroup[$group->id] ?? 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div
                    class="flex min-w-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white"
                    x-data="jobloadEvaluationChart({{ \Illuminate\Support\Js::from([
                        'labels' => $evaluation->pluck('label'),
                        'hours' => $evaluation->pluck('hours'),
                        'percents' => $evaluation->pluck('percent'),
                    ]) }})"
                >
                    <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-100 p-3">
                        <div>
                            <div class="text-sm font-medium text-gray-900">{{ $evalTitle }} · {{ $year }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $selectedGroup ? __('Verteilung nach Jobtypen') : __('Verteilung nach Jobgruppen') }}
                            </div>
                        </div>
                        <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
                            <button type="button" @click="setType('pie')" :class="type === 'pie' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover'" class="px-2.5 py-1 font-medium">{{ __('Torte') }}</button>
                            <button type="button" @click="setType('bar')" :class="type === 'bar' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover'" class="border-l border-gray-300 px-2.5 py-1 font-medium">{{ __('Balken') }}</button>
                        </div>
                    </div>

                    @if ($evaluation->isEmpty())
                        <p class="p-6 text-sm text-gray-500">{{ __('Keine Stunden im gewählten Jahr erfasst.') }}</p>
                    @else
                        <div class="flex min-h-0 flex-1 flex-wrap items-start gap-6 overflow-y-auto p-4">
                            <div class="relative h-80 min-w-[22rem] flex-1"><canvas x-ref="canvas"></canvas></div>
                            <table class="min-w-[22rem] flex-1 text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-xs text-gray-500">
                                        <th class="py-1 pr-3 text-left font-medium">{{ $selectedGroup ? __('Jobtyp') : __('Jobgruppe') }}</th>
                                        <th class="px-3 py-1 text-right font-medium">{{ __('Stunden') }}</th>
                                        <th class="py-1 pl-3 text-right font-medium">{{ __('Anteil') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($evaluation as $index => $item)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-1 pr-3">
                                                {{-- Hängender Einzug: umbrochener Text bleibt unter dem Text, nicht unter dem Farbfeld. --}}
                                                <div class="flex items-start gap-2">
                                                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-sm" :style="`background-color: ${color({{ $index }})}`"></span>
                                                    <span>{{ $item['label'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums">{{ $fmt($item['hours']) }}</td>
                                            <td class="py-1 pl-3 whitespace-nowrap text-right tabular-nums">{{ number_format($item['percent'], 1, ',', '.') }} %</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-semibold text-gray-800">
                                        <td class="py-1 pr-3">{{ __('Summe') }}</td>
                                        <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums">{{ $fmt($evaluationTotal) }}</td>
                                        <td class="py-1 pl-3 whitespace-nowrap text-right tabular-nums">100,0 %</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
            <script>
                document.addEventListener('alpine:init', () => {
                    // Chart.js-Objekte bewusst außerhalb des reaktiven Alpine-Zustands
                    // (Proxys vertragen sich schlecht mit Chart.js).
                    Alpine.data('jobloadEvaluationChart', (data) => { let chart = null; let ChartClass = null; return ({
                        type: 'pie',
                        palette: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#ec4899', '#84cc16', '#64748b'],
                        color(index) { return this.palette[index % this.palette.length]; },
                        async init() {
                            try { this.type = localStorage.getItem('jobload-evaluation-type') === 'bar' ? 'bar' : 'pie'; } catch (error) { /* ohne Web Storage: Torte */ }
                            ChartClass = await window.loadChartJs();
                            // $refs sind in init() noch nicht befüllt (Kinder werden erst danach
                            // von Alpine initialisiert) - deshalb erst nach dem Nachladen prüfen.
                            await new Promise((resolve) => setTimeout(resolve, 0));
                            this.render();
                        },
                        setType(type) {
                            this.type = type;
                            try { localStorage.setItem('jobload-evaluation-type', type); } catch (error) { /* egal */ }
                            this.render();
                        },
                        render() {
                            if (!ChartClass || !this.$refs.canvas) return;
                            chart?.destroy();
                            const isPie = this.type === 'pie';
                            const format = (value) => value.toLocaleString('de-DE', { minimumFractionDigits: {{ $hourDecimals }}, maximumFractionDigits: {{ $hourDecimals }} });
                            // Ralf, 2026-09-19: Prozentwerte direkt im Diagramm - auf den Tortenstücken
                            // (kleine Stücke unter 4 % bleiben unbeschriftet, sonst überlappen sie),
                            // über den Balken zusätzlich mit den Stunden. Eigenes kleines Plugin statt
                            // eines weiteren Pakets.
                            const valueLabels = {
                                id: 'valueLabels',
                                afterDatasetsDraw: (instance) => {
                                    const ctx = instance.ctx;
                                    instance.getDatasetMeta(0).data.forEach((element, index) => {
                                        const percent = data.percents[index];
                                        const percentText = percent.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' %';
                                        ctx.save();
                                        ctx.textAlign = 'center';
                                        if (isPie) {
                                            if (percent >= 4) {
                                                const position = element.tooltipPosition();
                                                ctx.textBaseline = 'middle';
                                                ctx.font = 'bold 13px sans-serif';
                                                ctx.fillStyle = '#ffffff';
                                                ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
                                                ctx.shadowBlur = 3;
                                                ctx.fillText(percentText, position.x, position.y);
                                            }
                                        } else {
                                            ctx.textBaseline = 'bottom';
                                            ctx.fillStyle = '#374151';
                                            ctx.font = 'bold 12px sans-serif';
                                            ctx.fillText(percentText, element.x, element.y - 16);
                                            ctx.font = '11px sans-serif';
                                            ctx.fillText(`${format(data.hours[index])} h`, element.x, element.y - 4);
                                        }
                                        ctx.restore();
                                    });
                                },
                            };
                            chart = new ChartClass(this.$refs.canvas, {
                                type: this.type,
                                plugins: [valueLabels],
                                data: {
                                    labels: data.labels,
                                    datasets: [{ data: data.hours, backgroundColor: data.labels.map((_, index) => this.color(index)), borderWidth: isPie ? 1 : 0 }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    layout: { padding: { top: isPie ? 0 : 36 } },
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: (context) => `${format(context.parsed.y ?? context.parsed)} h · ${data.percents[context.dataIndex].toLocaleString('de-DE', { maximumFractionDigits: 1 })} %`,
                                            },
                                        },
                                    },
                                    // Achsenbeschriftung gekürzt (volle Namen stehen in der Tabelle und im Tooltip),
                                    // sonst drücken lange Jobtyp-Namen das Diagramm klein.
                                    scales: isPie ? {} : {
                                        x: { ticks: { maxRotation: 0, autoSkip: false, callback(value) { const label = this.getLabelForValue(value); return label.length > 10 ? label.slice(0, 9) + '…' : label; } } },
                                        y: { beginAtZero: true, title: { display: true, text: {{ \Illuminate\Support\Js::from(__('Stunden')) }} } },
                                    },
                                },
                            });
                        },
                    }); });
                });
            </script>
        @else
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
        @endif
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
                requestAnimationFrame(() => { if (jobloadScroll) jobloadScroll.scrollLeft = Number(savedScroll) || 0; });
            }
        } catch (error) {
            // Die Übersicht bleibt auch ohne Web Storage nutzbar.
        }
        window.submitJobloadOverview = function (form) {
            try {
                sessionStorage.setItem(jobloadScrollKey, String(jobloadScroll?.scrollLeft ?? 0));
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
