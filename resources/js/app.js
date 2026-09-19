
import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';
import columnResize from './column-resize';

Alpine.plugin(sort);
Alpine.data('columnResize', columnResize);

window.Alpine = Alpine;

// Chart.js erst bei Bedarf nachladen (Zeiterfassungs-Auswertung), nicht auf jeder Seite.
// Muss VOR Alpine.start() definiert sein, denn Alpine ruft init() der Komponenten schon
// während des Starts auf.
window.loadChartJs = () => import('chart.js/auto').then((module) => module.default);

Alpine.start();
