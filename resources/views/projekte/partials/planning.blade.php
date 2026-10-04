@php
    [
        'planningGroups' => $planningGroups,
        'entriesByGroup' => $entriesByGroup,
        'plannedValues' => $plannedValues,
        'personValues' => $personValues,
    ] = \App\Support\ProjectPlanningGroups::for($project, $allFunctionGroups);
@endphp

<div
    class="{{ $isOverlay ? 'flex h-full min-h-0 flex-col gap-2' : 'space-y-2' }} text-sm"
    x-data="{
        planned: {{ \Illuminate\Support\Js::from($plannedValues) }},
        values: {{ \Illuminate\Support\Js::from($personValues) }},
        distributing: false,
        util: { view: 'month', year: {{ now()->year }}, month: {{ now()->month }}, person: '', loading: false, loaded: false, people: [] },
        utilCharts: [],
        subTab: window.projectPlanungSubTab || 'planstunden',
        init() {
            this.$watch('subTab', (value) => window.projectPlanungSubTab = value);
            this.onPeopleChanged = (e) => {
                if (e.detail.projectId === {{ $project->id }}) this.refreshPeople();
            };
            window.addEventListener('project-people-changed', this.onPeopleChanged);
            this.onPlannedHoursChanged = () => this.refreshPeople();
            window.addEventListener('project-planned-hours-changed', this.onPlannedHoursChanged);
        },
        destroy() {
            window.removeEventListener('project-people-changed', this.onPeopleChanged);
            window.removeEventListener('project-planned-hours-changed', this.onPlannedHoursChanged);
        },
        async refreshPeople() {
            const container = document.getElementById('project-planning-groups-{{ $project->id }}');
            if (! container) return;
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.planung.gruppen', ['project' => $project, 'overlay' => $isOverlay ? 1 : 0])) }}, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (! response.ok) return;
            const data = await response.json();
            const merged = {};
            Object.keys(data.values).forEach((groupId) => {
                merged[groupId] = {};
                Object.keys(data.values[groupId]).forEach((personId) => {
                    const current = (this.values[groupId] || {})[personId];
                    merged[groupId][personId] = current !== undefined ? current : data.values[groupId][personId];
                });
            });
            container.innerHTML = data.html;
            this.values = merged;
            this.planned = data.planned;
            window.resnapshotProjectOverlay?.();
        },
        async loadUtilization() {
            const container = this.$refs.utilBody;
            if (! container) return;
            this.util.loading = true;
            const query = new URLSearchParams({ view: this.util.view, year: this.util.year, month: this.util.month });
            if (this.util.person) query.set('person', this.util.person);
            try {
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.planung.auslastung', $project)) }} + '?' + query.toString(), { headers: { 'Accept': 'application/json' } });
                if (! response.ok) return;
                const data = await response.json();
                this.utilCharts.forEach((chart) => chart.destroy());
                this.utilCharts = [];
                container.innerHTML = data.html;
                this.util.people = data.people;
                this.util.loaded = true;
                await this.drawUtilizationCharts(container);
            } finally {
                this.util.loading = false;
            }
        },
        async drawUtilizationCharts(container) {
            const Chart = await window.loadChartJs();
            const names = {{ \Illuminate\Support\Js::from(['work' => __('Arbeitszeit'), 'absence' => __('Abwesenheit'), 'holiday' => __('Feiertag'), 'base' => __('Grundlast'), 'project' => __('Projekt'), 'over' => __('Überbuchung'), 'week' => __('KW')]) }};
            const weekly = this.util.view === 'year';
            container.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
                const d = JSON.parse(canvas.dataset.chart);
                const set = (label, data, color, stack) => ({ label, data, backgroundColor: color, stack, borderWidth: 0, categoryPercentage: 0.92, barPercentage: 1 });
                this.utilCharts.push(new Chart(canvas, {
                    type: 'bar',
                    data: { labels: d.labels, datasets: [
                        set(names.work, d.work, '#4ade80', 'kapazitaet'),
                        set(names.absence, d.absence, '#fbbf24', 'kapazitaet'),
                        set(names.holiday, d.holiday, '#a5b4fc', 'kapazitaet'),
                        set(names.base, d.base_load, '#94a3b8', 'belegung'),
                        set(names.project, d.project_in, '#3b82f6', 'belegung'),
                        set(names.over, d.project_over, '#ef4444', 'belegung'),
                    ] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true, grid: { display: false } },
                            y: { stacked: true, beginAtZero: true, title: { display: true, text: canvas.dataset.unit } },
                        },
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: { callbacks: { title: (items) => weekly ? names.week + ' ' + items[0].label + ' (' + d.subs[items[0].dataIndex] + ')' : d.subs[items[0].dataIndex] + ' ' + items[0].label } },
                        },
                    },
                }));
            });
        },
        number(value) { return value === '' || value === null || Number.isNaN(Number(value)) ? 0 : Number(value); },
        groupSum(groupId) { return Object.values(this.values[groupId] || {}).reduce((sum, value) => sum + this.number(value), 0); },
        groupDifference(groupId) { return this.number(this.planned[groupId]) - this.groupSum(groupId); },
        totalPlanned() { return Object.values(this.planned).reduce((sum, value) => sum + this.number(value), 0); },
        totalDistributed() { return Object.keys(this.planned).reduce((sum, groupId) => sum + this.groupSum(groupId), 0); },
        async distributeHours() {
            if (! await window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Planstunden verteilen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Bereits eingetragene Stunden werden dabei überschrieben. Möchten Sie die Planstunden trotzdem automatisch verteilen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Verteilen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            })) return;

            this.distributing = true;

            Object.keys(this.planned).forEach((groupId) => {
                const personIds = Object.keys(this.values[groupId] || {});
                if (personIds.length === 0) return;

                const totalCents = Math.round(this.number(this.planned[groupId]) * 100);
                const baseCents = Math.floor(totalCents / personIds.length);
                const remainderCents = totalCents - (baseCents * personIds.length);

                personIds.forEach((personId, index) => {
                    this.values[groupId][personId] = (baseCents + (index < remainderCents ? 1 : 0)) / 100;
                });
            });
            this.notifyChanged();
            await Alpine.nextTick();
            document.getElementById('project-detail-form')?.requestSubmit();
        },
        format(value) { return this.number(value).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        differenceClass(value) {
            return Math.abs(value) < 0.005
                ? 'border-green-200 bg-green-50 text-green-700'
                : (value > 0 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-red-200 bg-red-50 text-red-700');
        },
        notifyChanged() {
            document.getElementById('project-detail-form')?.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }"
>
    {{-- Unterreiter wie im Tab "Zeiten" (Ralf, 2026-10-03): Planstunden (bisheriger Inhalt) | Terminplan. --}}
    <div class="shrink-0 flex gap-1 border-b border-gray-100">
        <button type="button" @click="subTab = 'planstunden'" :class="subTab === 'planstunden' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-3 py-1.5 text-xs font-medium">{{ __('Planstunden') }}</button>
        @can('planning.view')
            <button type="button" @click="subTab = 'auslastung'; if (! util.loaded) loadUtilization()" :class="subTab === 'auslastung' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-3 py-1.5 text-xs font-medium">{{ __('Auslastung') }}</button>
        @endcan
        <button type="button" @click="subTab = 'terminplan'" :class="subTab === 'terminplan' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-3 py-1.5 text-xs font-medium">{{ __('Terminplan') }}</button>
    </div>

    <div x-show="subTab === 'planstunden'" class="{{ $isOverlay ? 'flex min-h-0 flex-1 flex-col gap-2' : 'space-y-2' }}">
        @include('projekte.partials.planned-hours-editor')

        @can('planning.view')
        <div class="shrink-0 flex items-start justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Planstunden und Verteilung auf Projektbeteiligte') }}</h3>
                <p class="text-xs text-gray-500">
                    @if ($project->functionGroupHours->isNotEmpty())
                        {{ __('Die Planstunden wurden von der Aufwandsschablone gelöst und gelten nur für dieses Projekt.') }}
                    @elseif ($project->projectTemplate)
                        {{ __('Grundlage: Aufwandsschablone „:name“', ['name' => $project->projectTemplate->name]) }}
                    @else
                        {{ __('Diesem Projekt ist keine Aufwandsschablone zugewiesen.') }}
                    @endif
                </p>
                @php $compression = app(\App\Services\ProjectPlanningCalculator::class)->compressionNotice($project); @endphp
                @if ($compression)
                    <p class="mt-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800" title="{{ __('Die Dauern der Workflow-Schritte (in Arbeitstagen) ergeben zusammen mehr, als das Projekt zwischen Start und Ende hat. Die Ressourcenplanung rechnet deshalb mit proportional verkürzten Schritten.') }}">
                        {{ __('Verdichtet: Die Schrittdauern (:sum AT) passen nicht in den Projektzeitraum (:available AT) und werden in der Ressourcenplanung entsprechend verdichtet.', ['sum' => number_format($compression['sum'], 0, ',', '.'), 'available' => $compression['available']]) }}
                    </p>
                @endif
                <button
                    type="button"
                    title="{{ __('Stunden werden pro Funktionsgruppe automatisch auf alle Personen gleichmäßig verteilt') }}"
                    :disabled="distributing"
                    @click="distributeHours()"
                    class="mt-1.5 rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-wait disabled:opacity-50"
                >{{ __('Std. verteilen') }}</button>
            </div>
            <div class="flex shrink-0 gap-2.5 text-xs text-gray-500">
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-amber-400"></span>{{ __('Noch zu verteilen') }}</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-green-400"></span>{{ __('Vollständig verteilt') }}</span>
                <span><span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-400"></span>{{ __('Mehr als geplant verteilt') }}</span>
            </div>
        </div>

        <div id="project-planning-groups-{{ $project->id }}" class="contents">
            @include('projekte.partials.planning-groups')
        </div>

        @endcan
    </div>

    @can('planning.view')
    <div x-show="subTab === 'auslastung'" x-cloak class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto' : '' }}">
        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
            <div class="inline-flex overflow-hidden rounded-md border border-gray-300">
                <button type="button" @click="util.view = 'month'; loadUtilization()" :class="util.view === 'month' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover'" class="px-3 py-1 font-medium">{{ __('Monat') }}</button>
                <button type="button" @click="util.view = 'year'; loadUtilization()" :class="util.view === 'year' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover'" class="border-l border-gray-300 px-3 py-1 font-medium">{{ __('Jahr') }}</button>
            </div>
            <select x-show="util.view === 'month'" x-model.number="util.month" @change="loadUtilization()" class="rounded-md border-gray-300 py-1 text-xs">
                @foreach (range(1, 12) as $monthNumber)
                    <option value="{{ $monthNumber }}">{{ \Carbon\CarbonImmutable::create(2000, $monthNumber, 1)->translatedFormat('F') }}</option>
                @endforeach
            </select>
            <select x-model.number="util.year" @change="loadUtilization()" class="rounded-md border-gray-300 py-1 text-xs">
                @foreach (range(2026, (int) now()->year + 5) as $yearNumber)
                    <option value="{{ $yearNumber }}">{{ $yearNumber }}</option>
                @endforeach
            </select>
            <select x-model="util.person" @change="loadUtilization()" class="rounded-md border-gray-300 py-1 text-xs">
                <option value="">{{ __('Alle Personen') }}</option>
                <template x-for="person in util.people" :key="person.id"><option :value="person.id" x-text="person.name"></option></template>
            </select>
            <span x-show="util.loading" x-cloak><x-loading-spinner class="h-4 w-4" /></span>
            <span class="text-gray-400">{{ __('Nur dieses Projekt; bei einem Hauptprojekt mit allen Unterprojekten. Mehrere Projekte zusammen: Planung.') }}</span>
        </div>
        <div x-ref="utilBody"></div>
    </div>
    @endcan

    <div x-show="subTab === 'terminplan'" x-cloak class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto' : '' }}">
        @include('projekte.partials.terminplan')
    </div>
</div>
