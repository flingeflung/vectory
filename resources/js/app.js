

import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';

Alpine.plugin(sort);

window.Alpine = Alpine;

Alpine.start();

// Chart.js erst bei Bedarf nachladen (Zeiterfassungs-Auswertung), nicht auf jeder Seite.
window.loadChartJs = () => import('chart.js/auto').then((module) => module.default);
