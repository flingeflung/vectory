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
                <option value="all" @selected($showAllPeople)>{{ __('– alle –') }}</option>
                @foreach ($people as $item)
                    <option value="{{ $item->id }}" @selected($person?->id === $item->id)>{{ $item->last_name }}, {{ $item->first_name }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <div class="min-h-0 flex-1 rounded-lg border border-gray-200 bg-white p-4">
        @if ($datasets->isNotEmpty())
            <div
                class="h-full min-h-80"
                x-data="planningWorkingHoursChart({{ \Illuminate\Support\Js::from($datasets) }}, {{ \Illuminate\Support\Js::from($showAllPeople) }})"
            >
                <canvas x-ref="canvas"></canvas>
            </div>
        @else
            <div class="flex h-full min-h-80 items-center justify-center text-sm text-gray-400">
                {{ __('Für :year sind keine Personen mit gültigen Wochenstunden-Daten sichtbar.', ['year' => $year]) }}
            </div>
        @endif
    </div>

    @if ($datasets->isNotEmpty())
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('planningWorkingHoursChart', (sourceDatasets, showAllPeople) => {
                    let chart = null;
                    let ChartClass = null;
                    const year = {{ $year }};
                    const dayMs = 86400000;
                    const yearStart = Date.UTC(year, 0, 1);
                    const monthStarts = Array.from({ length: 13 }, (_, month) => (Date.UTC(year, month, 1) - yearStart) / dayMs);
                    const monthCenters = monthStarts.slice(0, 12).map((start, month) => (start + monthStarts[month + 1]) / 2);
                    const monthFormatter = new Intl.DateTimeFormat(document.documentElement.lang || 'de-DE', { month: 'short', timeZone: 'UTC' });
                    const monthLabels = Array.from({ length: 12 }, (_, month) => monthFormatter.format(new Date(Date.UTC(year, month, 1))));
                    const dayCount = monthStarts[12];
                    const colorFor = (index) => `hsl(${Math.round((index * 137.508 + 218) % 360)} 68% 45%)`;
                    const expandPoints = (points) => {
                        const changeDays = new Set(points.filter((point) => !point.terminal).map((point) => Number(point.x)));
                        let value = 0;
                        let pointIndex = 0;
                        return Array.from({ length: dayCount + 1 }, (_, day) => {
                            while (pointIndex < points.length && Number(points[pointIndex].x) <= day) {
                                value = Number(points[pointIndex].y);
                                pointIndex++;
                            }
                            return { x: day, y: value, change: changeDays.has(day) };
                        });
                    };
                    const chartDatasets = sourceDatasets.map((dataset, index) => ({
                        label: dataset.label,
                        personId: dataset.personId,
                        data: expandPoints(dataset.points),
                        stepped: 'before',
                        borderColor: colorFor(index),
                        originalBorderColor: colorFor(index),
                        backgroundColor: showAllPeople ? 'transparent' : 'rgba(37, 99, 235, 0.08)',
                        borderWidth: 2,
                        pointRadius: (context) => context.raw?.change ? 3 : 0,
                        pointHoverRadius: (context) => context.raw?.change ? 5 : 0,
                        pointHitRadius: 5,
                        fill: !showAllPeople,
                    }));

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
                                    datasets: chartDatasets,
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    parsing: false,
                                    layout: { padding: { bottom: 24 } },
                                    interaction: { intersect: false, mode: 'index', axis: 'x' },
                                    plugins: {
                                        legend: {
                                            display: showAllPeople,
                                            position: 'top',
                                            align: 'start',
                                            labels: { usePointStyle: true, pointStyle: 'line', boxWidth: 22, boxHeight: 8, padding: 12 },
                                            onHover: (event, item, legend) => {
                                                legend.chart.canvas.style.cursor = 'pointer';
                                                legend.chart.data.datasets.forEach((dataset, index) => {
                                                    dataset.borderColor = index === item.datasetIndex ? dataset.originalBorderColor : 'rgba(156, 163, 175, 0.18)';
                                                    dataset.borderWidth = index === item.datasetIndex ? 4 : 1;
                                                });
                                                legend.chart.update('none');
                                            },
                                            onLeave: (event, item, legend) => {
                                                legend.chart.canvas.style.cursor = 'default';
                                                legend.chart.data.datasets.forEach((dataset) => {
                                                    dataset.borderColor = dataset.originalBorderColor;
                                                    dataset.borderWidth = 2;
                                                });
                                                legend.chart.update('none');
                                            },
                                        },
                                        tooltip: {
                                            filter: (item, index, items) => items.findIndex((candidate) => Math.abs(candidate.parsed.y - item.parsed.y) < 0.001) === index,
                                            callbacks: {
                                                title: (items) => {
                                                    if (!items.length) return '';
                                                    const dayOffset = Math.min(Math.round(items[0].parsed.x), monthStarts[12] - 1);
                                                    const date = new Date(yearStart + dayOffset * dayMs);
                                                    return date.toLocaleDateString(document.documentElement.lang || 'de-DE');
                                                },
                                                label: (context) => {
                                                    const sameValueNames = context.tooltip.dataPoints
                                                        .filter((item) => Math.abs(item.parsed.y - context.parsed.y) < 0.001)
                                                        .map((item) => item.dataset.label);
                                                    const hours = `${context.parsed.y.toLocaleString('de-DE', { maximumFractionDigits: 1 })} h`;
                                                    return showAllPeople ? `${hours}: ${sameValueNames.join(', ')}` : hours;
                                                },
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
                                            max: {{ $yMax }},
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
