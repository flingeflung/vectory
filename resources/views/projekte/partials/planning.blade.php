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
        utilBounds: { minYear: {{ min(2026, (int) ($project->start_date?->year ?? 2026)) }}, maxYear: {{ (int) now()->year + 5 }}, startYear: {{ $project->start_date?->year ?? 'null' }}, startMonth: {{ $project->start_date?->month ?? 'null' }} },
        util: { view: 'month', year: {{ now()->year }}, month: {{ now()->month }}, person: '', loading: false, loaded: false, people: [] },
        subTab: window.projectPlanungSubTab || 'planstunden',
        init() {
            this.$watch('subTab', (value) => window.projectPlanungSubTab = value);
            if (this.subTab === 'auslastung') this.$nextTick(() => this.loadUtilization());
            this.onPeopleChanged = (e) => {
                if (e.detail.projectId === {{ $project->id }}) this.refreshPeople();
            };
            window.addEventListener('project-people-changed', this.onPeopleChanged);
            this.onPlannedHoursChanged = () => this.refreshPeople();
            window.addEventListener('project-planned-hours-changed', this.onPlannedHoursChanged);
        },
        destroy() {
            this.destroyUtilCharts();
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
        shiftUtil(step) {
            if (this.util.view === 'month') {
                let month = this.util.month + step;
                let year = this.util.year;
                if (month < 1) { month = 12; year -= 1; }
                if (month > 12) { month = 1; year += 1; }
                if (year < this.utilBounds.minYear || year > this.utilBounds.maxYear) return;
                this.util.month = month;
                this.util.year = year;
            } else {
                const year = this.util.year + step;
                if (year < this.utilBounds.minYear || year > this.utilBounds.maxYear) return;
                this.util.year = year;
            }
            this.loadUtilization();
        },
        utilToday() {
            this.util.year = {{ now()->year }};
            this.util.month = {{ now()->month }};
            this.loadUtilization();
        },
        utilProjectStart() {
            if (this.utilBounds.startYear === null) return;
            this.util.year = this.utilBounds.startYear;
            this.util.month = this.utilBounds.startMonth;
            this.loadUtilization();
        },
        async loadUtilization() {
            const container = this.$refs.utilBody;
            if (! container) return;
            this.util.loading = true;
            const query = new URLSearchParams({ view: this.util.view, year: this.util.year, month: this.util.month });
            if (this.util.person) query.set('person', this.util.person);
            try {
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.planung.auslastung', $project)) }} + '?' + query.toString(), { headers: { 'Accept': 'application/json' } });
                if (! response.ok) {
                    this.showUtilError({{ \Illuminate\Support\Js::from(__('Die Auslastung konnte nicht geladen werden.')) }} + ' (' + response.status + ')');
                    return;
                }
                const data = await response.json();
                this.destroyUtilCharts();
                container.innerHTML = data.html;
                this.util.people = data.people;
                this.util.loaded = true;
                await this.drawUtilizationCharts(container);
            } catch (error) {
                this.showUtilError({{ \Illuminate\Support\Js::from(__('Die Auslastung konnte nicht geladen werden.')) }} + ' ' + String(error?.message || error));
            } finally {
                this.util.loading = false;
            }
        },
        // Chart-Objekte bewusst NICHT im Alpine-Zustand halten (Proxy-Umhüllung führte zu einem Überlauf beim Neuladen)
        utilChartStore() {
            window.__projectUtilCharts = window.__projectUtilCharts || {};
            return window.__projectUtilCharts[{{ $project->id }}] = window.__projectUtilCharts[{{ $project->id }}] || [];
        },
        destroyUtilCharts() {
            this.utilChartStore().splice(0).forEach((chart) => chart.destroy());
        },
        showUtilError(message) {
            const note = document.createElement('p');
            note.className = 'rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700';
            note.textContent = message;
            this.$refs.utilBody.replaceChildren(note);
        },
        async drawUtilizationCharts(container) {
            const Chart = await window.loadChartJs();
            const names = {{ \Illuminate\Support\Js::from(['capacity' => __('Arbeitszeit (verfügbar)'), 'base' => __('Grundlast'), 'project' => __('Projekt'), 'over' => __('Überbuchung'), 'week' => __('KW')]) }};
            const weekly = this.util.view === 'year';
            container.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
                this.utilChartStore().push(window.drawUtilizationChart(Chart, canvas, names, weekly));
            });
        },
        number(value) { return value === '' || value === null || Number.isNaN(Number(value)) ? 0 : Number(value); },
        groupSum(groupId) { return Object.values(this.values[groupId] || {}).reduce((sum, value) => sum + this.number(value), 0); },
        groupDifference(groupId) { return this.number(this.planned[groupId]) - this.groupSum(groupId); },
        totalPlanned() { return Object.values(this.planned).reduce((sum, value) => sum + this.number(value), 0); },
        totalDistributed() { return Object.keys(this.planned).reduce((sum, groupId) => sum + this.groupSum(groupId), 0); },
        async distributeHours(onlyGroupId = null) {
            if (! await window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Planstunden verteilen?')) }},
                message: onlyGroupId === null
                    ? {{ \Illuminate\Support\Js::from(__('Bereits eingetragene Stunden werden dabei überschrieben. Möchten Sie die Planstunden trotzdem automatisch verteilen?')) }}
                    : {{ \Illuminate\Support\Js::from(__('Bereits eingetragene Stunden dieser Funktionsgruppe werden dabei überschrieben. Möchten Sie die Planstunden der Funktionsgruppe trotzdem automatisch verteilen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Verteilen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            })) return;

            this.distributing = true;

            Object.keys(this.planned).filter((groupId) => onlyGroupId === null || String(groupId) === String(onlyGroupId)).forEach((groupId) => {
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

    <div x-show="subTab === 'planstunden'" data-help-tab="planung.planstunden" class="{{ $isOverlay ? 'flex min-h-0 flex-1 flex-col gap-2' : 'space-y-2' }}">
        @include('projekte.partials.planned-hours-editor')

        @can('planning.view')
        <div class="shrink-0 flex items-start justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Planstunden und Verteilung auf Projektbeteiligte') }}</h3>
                <p class="text-xs text-gray-500">
                    @if ($project->functionGroupHours->isNotEmpty())
                        {{ __('Die Planstunden wurden vom Aufwandsprofil gelöst und gelten nur für dieses Projekt.') }}
                    @elseif ($project->projectTemplate)
                        {{ __('Grundlage: Aufwandsprofil „:name“', ['name' => $project->projectTemplate->name]) }}
                    @else
                        {{ __('Diesem Projekt ist kein Aufwandsprofil zugewiesen.') }}
                    @endif
                </p>
                @php $compression = app(\App\Services\ProjectPlanningCalculator::class)->compressionNotice($project); @endphp
                @if ($compression)
                    <p class="mt-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-800" title="{{ __('Die Dauern der Workflow-Schritte (in Arbeitstagen) ergeben zusammen mehr, als das Projekt zwischen Start und Ende hat. Die Ressourcenplanung rechnet deshalb mit proportional verkürzten Schritten.') }}">
                        {{ __('Verdichtet: Der errechnete Zeitbedarf (:sum AT) überschreitet den verfügbaren Projektzeitraum (:available AT) und wird für die Ressourcenplanung entsprechend komprimiert. Dadurch können höhere Stundenaufwände pro Tag und Person entstehen.', ['sum' => number_format($compression['sum'], 0, ',', '.'), 'available' => $compression['available']]) }}
                    </p>
                @endif
                <button
                    type="button"
                    x-show="Object.keys(planned).length > 0"
                    title="{{ __('Stunden werden pro Funktionsgruppe automatisch auf alle Personen gleichmäßig verteilt') }}"
                    :disabled="distributing"
                    @click="distributeHours()"
                    class="mt-1.5 rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-wait disabled:opacity-50"
                >{{ __('Alle Std. verteilen') }}</button>
            </div>
            <div x-show="Object.keys(planned).length > 0" class="flex shrink-0 gap-2.5 text-xs text-gray-500">
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
    <div x-show="subTab === 'auslastung'" x-cloak data-help-tab="planung.auslastung" class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto' : '' }}">
        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
            <div class="inline-flex overflow-hidden rounded-md border border-gray-300">
                <button type="button" @click="util.view = 'month'; loadUtilization()" :class="util.view === 'month' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover'" class="px-3 py-1 font-medium">{{ __('Monat') }}</button>
                <button type="button" @click="util.view = 'year'; loadUtilization()" :class="util.view === 'year' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover'" class="border-l border-gray-300 px-3 py-1 font-medium">{{ __('Jahr') }}</button>
            </div>
            <button type="button" @click="shiftUtil(-1)" title="{{ __('Zurück') }}" aria-label="{{ __('Zurück') }}" class="rounded-md border border-gray-300 bg-btn-secondary p-1 text-gray-600 hover:bg-btn-secondary-hover">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <select x-show="util.view === 'month'" x-model.number="util.month" @change="loadUtilization()" class="rounded-md border-gray-300 py-1 text-xs">
                @foreach (range(1, 12) as $monthNumber)
                    <option value="{{ $monthNumber }}">{{ \Carbon\CarbonImmutable::create(2000, $monthNumber, 1)->translatedFormat('F') }}</option>
                @endforeach
            </select>
            <select x-model.number="util.year" @change="loadUtilization()" class="rounded-md border-gray-300 py-1 text-xs">
                @foreach (range(min(2026, (int) ($project->start_date?->year ?? 2026)), (int) now()->year + 5) as $yearNumber)
                    <option value="{{ $yearNumber }}">{{ $yearNumber }}</option>
                @endforeach
            </select>
            <button type="button" @click="shiftUtil(1)" title="{{ __('Vor') }}" aria-label="{{ __('Vor') }}" class="rounded-md border border-gray-300 bg-btn-secondary p-1 text-gray-600 hover:bg-btn-secondary-hover">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>
            <button type="button" @click="utilToday()" class="rounded-md border border-gray-300 bg-btn-secondary px-2 py-1 font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Heute') }}</button>
            <button type="button" @click="utilProjectStart()" :disabled="utilBounds.startYear === null" title="{{ $project->start_date ? __('Springt zum Monat bzw. Jahr des Projektstarts') : __('Für dieses Projekt ist noch kein Startdatum eingetragen') }}" class="rounded-md border border-gray-300 bg-btn-secondary px-2 py-1 font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Projektanfang') }}</button>
            <select x-model="util.person" @change="loadUtilization()" class="rounded-md border-gray-300 py-1 text-xs">
                <option value="">{{ __('Alle Personen') }}</option>
                <template x-for="person in util.people" :key="person.id"><option :value="person.id" x-text="person.name"></option></template>
            </select>
            <span x-show="util.loading" x-cloak><x-loading-spinner class="h-4 w-4" /></span>
            <span class="text-gray-400">{{ __('Die folgenden Planungsdaten gelten ausschließlich für dieses Projekt bzw. bei einem Hauptprojekt für dieses mit allen Unterprojekten. Für die Gesamtübersicht aller Projekte siehe Hauptnavigation: Planung.') }}</span>
        </div>
        <div x-ref="utilBody"></div>
    </div>
    @endcan

    <div x-show="subTab === 'terminplan'" x-cloak data-help-tab="planung.terminplan" class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto' : '' }}">
        @include('projekte.partials.terminplan')
    </div>
</div>
