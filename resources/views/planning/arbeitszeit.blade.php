<x-planning-layout>
    <form method="GET" action="{{ route('planung.arbeitszeit') }}" class="mb-3 flex shrink-0 items-center gap-4 text-sm">
        <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
            <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex items-center gap-2 text-gray-700">{{ __('Person') }}
            <select name="person" onchange="this.form.submit()" class="min-w-56 rounded-md border-gray-300 py-1 text-sm" @disabled($people->isEmpty())>
                @foreach ($people as $item)
                    <option value="{{ $item->id }}" @selected($person?->id === $item->id)>{{ $item->last_name }}, {{ $item->first_name }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <div class="min-h-0 flex-1 rounded-lg border border-gray-200 bg-white p-4">
        @if ($person)
            <div
                class="h-full min-h-80"
                x-data="planningWorkingHoursChart({{ \Illuminate\Support\Js::from($points) }})"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        @else
            <div class="flex h-full min-h-80 items-center justify-center text-sm text-gray-400">
                {{ __('Für :year sind keine Personen mit gültigen Wochenstunden-Daten sichtbar.', ['year' => $year]) }}
            </div>
        @endif
    </div>

    @if ($person)
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('planningWorkingHoursChart', (points) => {
                    let chart = null;
                    let ChartClass = null;
                    const year = {{ $year }};
                    const dayMs = 86400000;
                    const yearStart = Date.UTC(year, 0, 1);
                    const monthStarts = Array.from({ length: 13 }, (_, month) => (Date.UTC(year, month, 1) - yearStart) / dayMs);
                    const monthCenters = monthStarts.slice(0, 12).map((start, month) => (start + monthStarts[month + 1]) / 2);
                    const monthLabels = [{{ collect(range(1, 12))->map(fn ($month) => \Illuminate\Support\Js::from(\Carbon\CarbonImmutable::create($year, $month, 1)->locale(app()->getLocale())->isoFormat('MMM')))->implode(', ') }}];

                    return {
                        async init() {
                            ChartClass = await window.loadChartJs();
                            await new Promise((resolve) => setTimeout(resolve, 0));
                            if (!this.$refs.canvas) return;

                            chart?.destroy();
                            const monthGrid = {
                                id: 'monthGrid',
                                beforeDatasetsDraw(instance) {
                                    const { ctx, chartArea, scales: { x } } = instance;
                                    ctx.save();
                                    ctx.strokeStyle = '#e5e7eb';
                                    ctx.lineWidth = 1;
                                    monthStarts.forEach((day) => {
                                        const pixel = x.getPixelForValue(day);
                                        ctx.beginPath();
                                        ctx.moveTo(pixel, chartArea.top);
                                        ctx.lineTo(pixel, chartArea.bottom);
                                        ctx.stroke();
                                    });
                                    ctx.restore();
                                },
                            };
                            chart = new ChartClass(this.$refs.canvas, {
                                type: 'line',
                                plugins: [monthGrid],
                                data: {
                                    datasets: [{
                                        label: {{ \Illuminate\Support\Js::from(__('Wochenstunden')) }},
                                        data: points,
                                        stepped: 'after',
                                        borderColor: '#2563eb',
                                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                                        borderWidth: 2,
                                        pointRadius: (context) => context.raw?.terminal ? 0 : 3,
                                        pointHoverRadius: (context) => context.raw?.terminal ? 0 : 5,
                                        fill: true,
                                    }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    parsing: false,
                                    interaction: { intersect: false, mode: 'nearest' },
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                title: (items) => {
                                                    if (!items.length) return '';
                                                    const dayOffset = Math.min(Math.round(items[0].parsed.x), monthStarts[12] - 1);
                                                    const date = new Date(yearStart + dayOffset * dayMs);
                                                    return date.toLocaleDateString(document.documentElement.lang || 'de-DE');
                                                },
                                                label: (context) => `${context.parsed.y.toLocaleString('de-DE', { maximumFractionDigits: 1 })} h`,
                                            },
                                        },
                                    },
                                    scales: {
                                        x: {
                                            type: 'linear',
                                            min: 0,
                                            max: monthStarts[12],
                                            afterBuildTicks: (axis) => {
                                                axis.ticks = monthCenters.map((value) => ({ value }));
                                            },
                                            grid: { display: false },
                                            ticks: {
                                                autoSkip: false,
                                                padding: 8,
                                                callback: (_value, index) => monthLabels[index] ?? '',
                                            },
                                        },
                                        y: {
                                            beginAtZero: true,
                                            title: { display: true, text: {{ \Illuminate\Support\Js::from(__('Wochenstunden')) }} },
                                            ticks: { callback: (value) => `${value.toLocaleString('de-DE')} h` },
                                        },
                                    },
                                },
                            });
                        },
                    };
                });
            });
        </script>
    @endif
</x-planning-layout>
