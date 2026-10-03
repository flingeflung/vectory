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

    <div x-show="subTab === 'terminplan'" x-cloak class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto' : '' }}">
        @include('projekte.partials.terminplan')
    </div>
</div>
