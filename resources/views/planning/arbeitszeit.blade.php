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
                    const monthFormatter = new Intl.DateTimeFormat(document.documentElement.lang || 'de-DE', { month: 'short', timeZone: 'UTC' });
                    const monthLabels = Array.from({ length: 12 }, (_, month) => monthFormatter.format(new Date(Date.UTC(year, month, 1))));

                    return {
                        async init() {
                            ChartClass = await window.loadChartJs();
                            await new Promise((resolve) => setTimeout(resolve, 0));
                            if (!this.$refs.canvas) return;

                            chart?.destroy();
                            const calendarGrid = {
                                id: 'calendarGrid',
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
                                afterDraw(instance) {
                                    const { ctx, chartArea, scales: { x } } = instance;
                                    ctx.save();
                                    ctx.fillStyle = '#4b5563';
                                    ctx.font = '12px sans-serif';
                                    ctx.textAlign = 'center';
                                    ctx.textBaseline = 'top';
                                    monthCenters.forEach((day, index) => {
                                        ctx.fillText(monthLabels[index], x.getPixelForValue(day), chartArea.bottom + 10);
                                    });
                                    ctx.restore();
                                },
                            };
                            chart = new ChartClass(this.$refs.canvas, {
                                type: 'line',
                                plugins: [calendarGrid],
                                data: {
                                    datasets: [{
                                        label: {{ \Illuminate\Support\Js::from(__('Wochenstunden')) }},
                                        data: points,
                                        stepped: 'before',
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
                                    layout: { padding: { bottom: 24 } },
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
                                            grid: { display: false },
                                            ticks: { display: false },
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
