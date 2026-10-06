// Auslastungs-Diagramm (Säulen: Grundlast + Projektstunden, Überbuchung rot; Linie: verfügbare Arbeitszeit).
// Gemeinsam genutzt vom Projekt-Reiter Planung › Auslastung und der Seite Planung › Projektplanung.
// canvas[data-chart] trägt die Daten (ProjectUtilization::chartData), data-label-width die Breite der Beschriftungsspalte.
const bandColors = { weekend: '#fffaeb', holiday: '#fdf2f8', absence: '#fef3c7' };

export function drawUtilizationChart(Chart, canvas, names, weekly) {
    const d = JSON.parse(canvas.dataset.chart);
    const labelWidth = Number(canvas.dataset.labelWidth || 0);

    const bands = {
        id: 'bands',
        beforeDatasetsDraw(chart) {
            const { ctx, chartArea, scales: { x } } = chart;
            d.flags.forEach((flag, index) => {
                if (! flag && index !== d.today) return;
                const left = x.getPixelForValue(index) - (x.getPixelForValue(1) - x.getPixelForValue(0)) / 2;
                const width = x.getPixelForValue(1) - x.getPixelForValue(0);
                ctx.save();
                ctx.fillStyle = index === d.today ? '#eff6ff' : bandColors[flag];
                ctx.fillRect(left, chartArea.top, width, chartArea.bottom - chartArea.top);
                if (index === d.today) {
                    ctx.strokeStyle = '#93c5fd';
                    ctx.lineWidth = 1;
                    ctx.strokeRect(left + 0.5, chartArea.top, width - 1, chartArea.bottom - chartArea.top);
                }
                ctx.restore();
            });
        },
    };

    // Verfügbare Arbeitszeit als waagerechte Linie (wie im Reiter Arbeitszeit); ohne Arbeitszeit liegt sie auf 0, nur Wochenenden und Feiertage bleiben leer
    const capacityLine = {
        id: 'capacityLine',
        afterDatasetsDraw(chart) {
            const { ctx, scales: { x, y } } = chart;
            const width = x.getPixelForValue(1) - x.getPixelForValue(0);
            ctx.save();
            ctx.strokeStyle = '#16a34a';
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'butt';
            d.capacity.forEach((hours, index) => {
                if (! hours && ['weekend', 'holiday'].includes(d.flags[index])) return;
                const left = x.getPixelForValue(index) - width / 2;
                const top = y.getPixelForValue(hours);
                ctx.beginPath();
                ctx.moveTo(left + 1, top);
                ctx.lineTo(left + width - 1, top);
                ctx.stroke();
            });
            ctx.restore();
        },
    };

    return new Chart(canvas, {
        type: 'bar',
        data: { labels: d.labels, datasets: [
            { label: names.base, data: d.base_load.map((v) => [0, v]), backgroundColor: '#94a3b8', borderWidth: 0, categoryPercentage: 0.9, barPercentage: 0.8, grouped: false },
            { label: names.project, data: d.project_in.map((v, i) => [d.base_load[i], d.base_load[i] + v]), backgroundColor: '#3b82f6', borderWidth: 0, categoryPercentage: 0.9, barPercentage: 0.8, grouped: false },
            { label: names.over, data: d.project_over.map((v, i) => [d.base_load[i] + d.project_in[i], d.base_load[i] + d.project_in[i] + v]), backgroundColor: '#ef4444', borderWidth: 0, categoryPercentage: 0.9, barPercentage: 0.8, grouped: false },
        ] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { right: 0 } },
            scales: {
                x: { display: false, grid: { display: false } },
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.max(...d.capacity, 1),
                    title: { display: true, text: canvas.dataset.unit },
                    // gleiche Breite wie die Beschriftungsspalte der Tabelle, damit die Tage senkrecht fluchten
                    afterFit(scale) { if (labelWidth) scale.width = labelWidth; },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: { filter: (item) => Math.abs(item.raw[1] - item.raw[0]) > 0.0001, callbacks: { label: (item) => item.dataset.label + ': ' + (item.raw[1] - item.raw[0]).toFixed(2), footer: (items) => names.capacity + ': ' + d.capacity[items[0].dataIndex], title: (items) => weekly ? names.week + ' ' + items[0].label + ' (' + d.subs[items[0].dataIndex] + ')' : d.subs[items[0].dataIndex] + ' ' + items[0].label } },
            },
        },
        plugins: [bands, capacityLine],
    });
}
