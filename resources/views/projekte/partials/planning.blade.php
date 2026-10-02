@php
    $effectiveGroupHours = $project->functionGroupHours->isNotEmpty()
        ? $project->functionGroupHours->pluck('pivot.planned_hours', 'id')
        : ($project->projectTemplate?->functionGroups?->pluck('pivot.planned_hours', 'id') ?? collect());
    $entriesByGroup = $project->projectPeople->groupBy('function_group_id');
    $planningGroups = $allFunctionGroups->filter(fn ($group) => $effectiveGroupHours->has($group->id) || $entriesByGroup->has($group->id));
    $plannedValues = $planningGroups->mapWithKeys(fn ($group) => [(string) $group->id => (float) ($effectiveGroupHours->get($group->id) ?? 0)]);
    $personValues = $planningGroups->mapWithKeys(fn ($group) => [
        (string) $group->id => ($entriesByGroup->get($group->id) ?? collect())->mapWithKeys(fn ($entry) => [
            (string) $entry->person_id => (float) ($entry->planned_hours ?? 0),
        ]),
    ]);
@endphp

<div
    class="{{ $isOverlay ? 'flex h-full min-h-0 flex-col gap-2' : 'space-y-2' }} text-sm"
    x-data="{
        planned: {{ \Illuminate\Support\Js::from($plannedValues) }},
        values: {{ \Illuminate\Support\Js::from($personValues) }},
        distributing: false,
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

    <div class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto pr-1' : '' }} space-y-2">
    @forelse ($planningGroups as $group)
        @php($entries = $entriesByGroup->get($group->id) ?? collect())
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="grid grid-cols-[minmax(0,1fr)_7rem_7rem_7rem] items-center gap-2 bg-gray-50 px-2.5 py-1.5">
                <div class="min-w-0">
                    <div class="truncate font-semibold text-gray-800" title="{{ $group->name }}">{{ $group->name }}</div>
                    <div class="text-xs text-gray-400">{{ $group->short_name }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] text-gray-400">{{ __('Geplant') }}</div>
                    <div class="font-semibold tabular-nums text-gray-900" x-text="format(planned['{{ $group->id }}']) + ' h'"></div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] text-gray-400">{{ __('Verteilt') }}</div>
                    <div class="font-semibold tabular-nums text-gray-900" x-text="format(groupSum('{{ $group->id }}')) + ' h'"></div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] text-gray-400">{{ __('Differenz') }}</div>
                    <span class="inline-flex min-w-[5.5rem] justify-end rounded border px-2 py-0.5 font-semibold tabular-nums" :class="differenceClass(groupDifference('{{ $group->id }}'))" x-text="format(groupDifference('{{ $group->id }}')) + ' h'"></span>
                </div>
            </div>
            <div class="divide-y divide-gray-100 px-2.5">
                @forelse ($entries as $entry)
                    <label class="grid grid-cols-[minmax(0,1fr)_7rem] items-center gap-2 py-1">
                        <span class="truncate {{ $entry->person->active ? 'text-gray-700' : 'text-gray-400' }}">
                            {{ $entry->person->fullName() }}{{ ! $entry->person->active ? ' [i]' : '' }} <x-absence-icon :person="$entry->person" />
                        </span>
                        <input
                            type="number"
                            name="project_people_hours[{{ $group->id }}][{{ $entry->person_id }}]"
                            form="project-detail-form"
                            x-model="values['{{ $group->id }}']['{{ $entry->person_id }}']"
                            value="{{ (float) ($entry->planned_hours ?? 0) }}"
                            @input="notifyChanged()"
                            min="0"
                            step="0.01"
                            class="w-full rounded border-gray-300 px-2 py-0.5 text-right text-xs tabular-nums"
                            title="{{ __('Geplante Stunden dieser Person') }}"
                            placeholder="{{ __('Std.') }}"
                        >
                    </label>
                @empty
                    <div class="py-1.5 text-gray-400">{{ __('Keine Person zugeordnet') }}</div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-6 text-center text-gray-500">{{ __('Keine Planstunden oder Projektbeteiligten vorhanden.') }}</div>
    @endforelse
    </div>

    @if ($planningGroups->isNotEmpty())
        <div class="shrink-0 grid grid-cols-[minmax(0,1fr)_7rem_7rem_7rem] items-center gap-2 border-t-2 border-gray-500 px-2.5 pt-2">
            <div class="font-semibold text-gray-900">{{ __('Summe') }}</div>
            <div class="text-right font-semibold tabular-nums text-gray-900" x-text="format(totalPlanned()) + ' h'"></div>
            <div class="text-right font-semibold tabular-nums text-gray-900" x-text="format(totalDistributed()) + ' h'"></div>
            <div class="text-right">
                <span class="inline-flex min-w-[5.5rem] justify-end rounded border px-2 py-0.5 font-semibold tabular-nums" :class="differenceClass(totalPlanned() - totalDistributed())" x-text="format(totalPlanned() - totalDistributed()) + ' h'"></span>
            </div>
        </div>
    @endif
    @endcan
</div>
