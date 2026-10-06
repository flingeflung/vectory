<div id="project-templates-content" class="flex flex-1 min-h-0 gap-4">
    {{-- Links: Liste, per Merkmal-Filter einschränkbar. --}}
    <div class="flex w-80 shrink-0 flex-col">
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
            <div class="shrink-0 space-y-1.5 border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Aufwandsprofile') }}</span>
                <div class="flex items-center gap-1">
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

            {{-- Ralf, 2026-09-19: Reihenfolge per Drag & Drop (Griff links), gespeichert beim Loslassen.
                 Mit aktivem Merkmal-Filter verschiebt man nur innerhalb der sichtbaren Schablonen. --}}
            <div
                class="flex-1 min-h-0 overflow-y-auto p-2 text-sm"
                x-data="{
                    async saveOrder() {
                        const ids = [...$el.querySelectorAll('[x-sort\\:item]')].map((el) => el.getAttribute('x-sort:item'));
                        await fetch({{ \Illuminate\Support\Js::from(route('admin.projektschablonen.reorder')) }}, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                            body: JSON.stringify({ templates: ids }),
                        });
                    },
                }"
                x-init="$nextTick(() => window.keepListScroll($el, 'list-scroll:project-templates'))"
                x-sort="saveOrder()"
            >
                @forelse ($templates as $template)
                    <div x-sort:item="{{ $template->id }}" class="flex items-center gap-1 rounded {{ $selectedTemplate?->id === $template->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                        <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                        <a
                            href="{{ route('admin.projektschablonen', ['schablone' => $template->id]) }}"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedTemplate?->id === $template->id) data-selected @endif
                            class="block min-w-0 flex-1 rounded py-1.5 pr-2 {{ $selectedTemplate?->id === $template->id ? 'font-medium text-indigo-700' : '' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate {{ $selectedTemplate?->id === $template->id ? '' : ($template->active ? 'text-gray-700' : 'text-gray-400') }}">{{ $template->name }}{{ ! $template->active ? ' [i]' : '' }}</span>
                                <span class="shrink-0 text-xs text-gray-400">{{ rtrim(rtrim((string) $template->duration_value, '0'), '.') }} {{ \App\Models\ProjectTemplate::durationUnitOptions()[$template->duration_unit] }}</span>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Aufwandsprofile angelegt.') }}</div>
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
                <div class="text-sm font-medium text-gray-900">{{ __('Neues Aufwandsprofil') }}</div>
            </div>
            <form
                method="POST"
                action="{{ route('admin.projektschablonen.store') }}"
                class="flex-1 min-h-0 overflow-y-auto p-3"
                x-data
                x-init="$nextTick(() => $refs.newName?.focus())"
            >
                @csrf
                @include('admin.project-templates.partials.fields-main', ['template' => null])
                <p class="mt-3 text-xs text-gray-400">{{ __('Die Planstunden je Funktionsgruppe tragen Sie nach dem Speichern ein, sobald ein Workflow gekoppelt ist.') }}</p>
                @include('admin.project-templates.partials.fields-characteristics', ['template' => null])
                @include('admin.project-templates.partials.fields-remarks', ['template' => null])
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
                            title: {{ \Illuminate\Support\Js::from(__('Aufwandsprofil klonen')) }},
                            message: {{ \Illuminate\Support\Js::from(__('Legt eine vollständige Kopie dieses Aufwandsprofils (inkl. Workflow-Kopplung und Planstunden je Funktionsgruppe) an. Die Kopie startet inaktiv.')) }},
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
                        @click="window.deleteWithConfirm($refs.deleteForm, { message: {{ \Illuminate\Support\Js::from(__('Dieses Aufwandsprofil wirklich endgültig löschen?')) }} })"
                        class="rounded-md border border-red-300 px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-4">
                @php($plannedHours = $template->functionGroups->mapWithKeys(fn ($fg) => [(string) $fg->id => (string) $fg->pivot->planned_hours]))
                <form
                    data-row-form
                    x-data="{ dirty: false, hours: {{ \Illuminate\Support\Js::from($plannedHours) }} }"
                    @input="dirty = window.formIsDirty($el, window.__projectTemplatesDirtyForms)"
                    @submit="dirty = false; window.__projectTemplatesDirtyForms.delete($el)"
                    method="POST"
                    action="{{ route('admin.projektschablonen.update', $template) }}"
                >
                    @csrf
                    @include('admin.project-templates.partials.fields-main', ['template' => $template])
                    @include('admin.project-templates.partials.hours', ['template' => $template])
                    @include('admin.project-templates.partials.fields-characteristics', ['template' => $template])
                    @include('admin.project-templates.partials.fields-remarks', ['template' => $template])
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
            </div>
        @else
            <div class="shrink-0 border-b border-gray-100 p-3">
                <div class="text-sm font-medium text-gray-900">{{ __('Aufwandsprofile') }}</div>
                <p class="text-xs text-gray-400">{{ __('Wählen Sie links ein Aufwandsprofil aus, um es zu bearbeiten, oder legen Sie ein neues an.') }}</p>
            </div>
        @endif
    </div>
</div>
