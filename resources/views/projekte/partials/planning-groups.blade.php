{{-- Gruppenliste + Summenzeile des Planungs-Tabs. Wird von planning.blade.php eingebunden
     und von ProjectController::planningGroups() allein gerendert, um den Bereich nach einer
     Änderung der Projektbeteiligten live nachzuladen. Erwartet $project, $planningGroups,
     $entriesByGroup, $isOverlay. --}}
    <div class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto pr-1' : '' }} space-y-2">
    @forelse ($planningGroups as $group)
        @php($entries = $entriesByGroup->get($group->id) ?? collect())
        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="grid grid-cols-[minmax(0,1fr)_7rem_7rem_7rem] items-center gap-2 bg-gray-50 px-2.5 py-1.5">
                <div class="flex min-w-0 items-center gap-2">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-gray-800" title="{{ $group->name }}">{{ $group->name }}</div>
                        <div class="text-xs text-gray-400">{{ $group->short_name }}</div>
                    </div>
                    @if ($entries->isNotEmpty())
                        <button
                            type="button"
                            x-show="number(planned['{{ $group->id }}']) > 0"
                            title="{{ __('Stunden dieser Funktionsgruppe automatisch auf alle ihre Personen gleichmäßig verteilen') }}"
                            :disabled="distributing"
                            @click="distributeHours('{{ $group->id }}')"
                            class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-wait disabled:opacity-50"
                        >{{ __('Std. verteilen') }}</button>
                    @endif
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
        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-6 text-center text-gray-500">{{ __('Keine Planstunden oder Projektbeteiligte vorhanden.') }}</div>
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
