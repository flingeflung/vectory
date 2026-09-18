<div id="project-templates-content" class="flex flex-1 min-h-0 gap-4">
    {{-- Links: Liste, per Merkmal-Filter einschränkbar. --}}
    <div class="flex w-80 shrink-0 flex-col">
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
            <div class="shrink-0 space-y-1.5 border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Projektschablonen') }}</span>
                <div class="flex items-center gap-1">
                    @if ($otherTenants->isNotEmpty())
                        <button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'projektschablonen-uebernehmen' }))"
                            class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                        >
                            {{ __('Von anderem Kunden importieren') }}
                        </button>
                    @endif
                    <a
                        href="{{ route('admin.projektschablonen', ['neu' => 1]) }}"
                        onclick="return window.navigateOrConfirm(event)"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >
                        + {{ __('Neu') }}
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.projektschablonen') }}" class="shrink-0 grid grid-cols-2 gap-1.5 border-b border-gray-100 p-2">
                @foreach (\App\Models\ProjectTemplate::filterableFields() as $field)
                    @php($meta = \App\Models\ProjectTemplate::characteristicFields()[$field])
                    <select name="{{ $field }}" onchange="this.form.submit()" title="{{ $meta['label'] }}" class="w-full rounded-md border-gray-300 py-1 text-xs">
                        <option value="">{{ $meta['label'] }}</option>
                        @foreach ($meta['options'] as $value => $option)
                            <option value="{{ $value }}" @selected((string) request($field) === (string) $value)>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                @endforeach
                @if (collect(\App\Models\ProjectTemplate::filterableFields())->contains(fn ($field) => request()->filled($field)))
                    <a href="{{ route('admin.projektschablonen') }}" class="col-span-2 text-center text-xs text-indigo-600 hover:text-indigo-800">{{ __('Filter löschen') }}</a>
                @endif
            </form>

            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-data x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                @forelse ($templates as $template)
                    <a
                        href="{{ route('admin.projektschablonen', ['schablone' => $template->id]) }}"
                        onclick="return window.navigateOrConfirm(event)"
                        @if ($selectedTemplate?->id === $template->id) data-selected @endif
                        class="block rounded px-2 py-1.5 {{ $selectedTemplate?->id === $template->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'hover:bg-gray-50' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate {{ $selectedTemplate?->id === $template->id ? '' : ($template->active ? 'text-gray-700' : 'text-gray-400') }}">{{ $template->name }}{{ ! $template->active ? ' [i]' : '' }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ rtrim(rtrim((string) $template->duration_value, '0'), '.') }} {{ \App\Models\ProjectTemplate::durationUnitOptions()[$template->duration_unit] }}</span>
                        </div>
                    </a>
                @empty
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Projektschablonen angelegt.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Rechts: Anlegen-Formular, die gewählte Schablone, oder ein Platzhalter. --}}
    <div
        x-data
        x-init="window.adminPageIsDirty = () => window.__projectTemplatesDirtyForms.size > 0"
        class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white"
    >
        @if ($creating)
            <div class="shrink-0 border-b border-gray-100 p-3">
                <div class="text-sm font-medium text-gray-900">{{ __('Neue Projektschablone') }}</div>
            </div>
            <form
                method="POST"
                action="{{ route('admin.projektschablonen.store') }}"
                class="flex-1 min-h-0 overflow-y-auto p-3"
                x-data
                x-init="$nextTick(() => $refs.newName?.focus())"
            >
                @csrf
                @include('admin.project-templates.partials.fields', ['template' => null])
                <div class="mt-3 flex justify-end gap-2">
                    <a href="{{ route('admin.projektschablonen') }}" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Abbrechen') }}
                    </a>
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </form>
        @elseif ($selectedTemplate)
            @php($template = $selectedTemplate)
            @php($createdByName = $template->createdByUser?->person?->fullName() ?? $template->createdByUser?->name ?? '–')
            @php($updatedByName = $template->updatedByUser?->person?->fullName() ?? $template->updatedByUser?->name)
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-3" x-data>
                <div class="text-sm font-medium text-gray-900">{{ $template->name }}</div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('admin.projektschablonen.duplicate', $template) }}" x-ref="duplicateForm" class="hidden">
                        @csrf
                    </form>
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.duplicateForm, {
                            title: {{ \Illuminate\Support\Js::from(__('Schablone klonen')) }},
                            message: {{ \Illuminate\Support\Js::from(__('Legt eine vollständige Kopie dieser Schablone (inkl. Workflow-Kopplung und Stunden je Funktionsgruppe) an. Die Kopie startet inaktiv.')) }},
                            confirmLabel: {{ \Illuminate\Support\Js::from(__('Klonen')) }},
                        })"
                        class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >
                        {{ __('Klonen') }}
                    </button>
                    <form method="POST" action="{{ route('admin.projektschablonen.destroy', $template) }}" x-ref="deleteForm" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, { message: {{ \Illuminate\Support\Js::from(__('Diese Projektschablone wirklich endgültig löschen?')) }} })"
                        class="rounded-md border border-red-300 px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-4">
                <form
                    data-row-form
                    x-data="{ dirty: false }"
                    @input="dirty = window.formIsDirty($el, window.__projectTemplatesDirtyForms)"
                    @submit="dirty = false; window.__projectTemplatesDirtyForms.delete($el)"
                    method="POST"
                    action="{{ route('admin.projektschablonen.update', $template) }}"
                >
                    @csrf
                    @include('admin.project-templates.partials.fields', ['template' => $template])
                    <div class="mt-2 flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            {{ __('angelegt von :name am :date', ['name' => $createdByName, 'date' => $template->created_at?->format('d.m.Y')]) }}
                            @if ($updatedByName)
                                , {{ __('geändert von :name am :date', ['name' => $updatedByName, 'date' => $template->updated_at?->format('d.m.Y')]) }}
                            @endif
                        </p>
                        <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </form>

                {{--
                    Step 2 der Kapa-Planung (Ralf, 2026-09-18): geplante
                    Stunden je Funktionsgruppe - eigenes Formular/eigener
                    Speichern-Vorgang (sync auf die Pivot-Tabelle). Welche
                    Fktgrp hier auftauchen, bestimmt Step 3
                    (Workflow-Kopplung) - Ralf-Korrektur 2026-09-18: "Zuerst
                    muss ein WF gekoppelt werden, erst dadurch ergeben sich
                    die Fktgrps", siehe ProjectTemplate::relevantFunctionGroups().
                --}}
                @php($relevantFunctionGroups = $template->relevantFunctionGroups())
                <div class="border-t border-gray-100 pt-3">
                    @if (! $template->workflow_id)
                        <p class="text-xs text-gray-400">{{ __('Erst einen Workflow koppeln, um Stunden je Funktionsgruppe zu planen.') }}</p>
                    @elseif ($relevantFunctionGroups->isEmpty())
                        <p class="text-xs text-gray-400">{{ __('Der gekoppelte Workflow hat noch keinen Schritten Funktionsgruppen zugeordnet.') }}</p>
                    @else
                        @php($plannedHours = $template->functionGroups->mapWithKeys(fn ($fg) => [(string) $fg->id => (string) $fg->pivot->planned_hours]))
                        <form
                            data-row-form
                            x-data="{ dirty: false, hours: {{ \Illuminate\Support\Js::from($plannedHours) }} }"
                            @input="dirty = window.formIsDirty($el, window.__projectTemplatesDirtyForms)"
                            @submit="dirty = false; window.__projectTemplatesDirtyForms.delete($el)"
                            method="POST"
                            action="{{ route('admin.projektschablonen.funktionsgruppen.update', $template) }}"
                        >
                            @csrf
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-medium text-gray-600">{{ __('Stunden je Funktionsgruppe') }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ __('Summe') }}: <span x-text="Object.values(hours).reduce((sum, v) => sum + (parseFloat(v) || 0), 0).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })"></span> h
                                </p>
                            </div>
                            {{--
                                Gestaltgesetz der Nähe (Ralf, 2026-09-18):
                                Label und Eingabefeld gehören sichtbar
                                zusammengefasst, nicht nur per Reihenfolge -
                                das vorherige flex-1 auf dem Namen drückte das
                                Feld an den rechten Zellenrand, wo es optisch
                                näher am NÄCHSTEN Label stand als am eigenen.
                                Eine umrandete Box je Fktgrp (statt reiner
                                Abstands-Steuerung) macht die Zuordnung
                                eindeutig, unabhängig von der Textlänge.
                            --}}
                            <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                                @foreach ($relevantFunctionGroups as $fg)
                                    <label class="flex items-center justify-between gap-1 rounded-md border border-gray-200 px-1.5 py-1 text-xs text-gray-600">
                                        <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                                        <input type="number" name="hours[{{ $fg->id }}]" x-model="hours['{{ $fg->id }}']" min="0" max="999" step="0.5" placeholder="–" class="w-20 shrink-0 rounded-md border-gray-300 py-0.5 text-xs">
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-1 flex justify-end">
                                <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                    {{ __('Speichern') }}
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @else
            <div class="shrink-0 border-b border-gray-100 p-3">
                <div class="text-sm font-medium text-gray-900">{{ __('Projektschablonen') }}</div>
                <p class="text-xs text-gray-400">{{ __('Wähle links eine Schablone aus, um sie zu bearbeiten, oder lege eine neue an.') }}</p>
            </div>
        @endif
    </div>
</div>
