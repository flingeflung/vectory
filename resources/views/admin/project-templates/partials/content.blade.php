<div x-data="{ creating: false }" class="space-y-2">
    <div x-show="!creating">
        <button type="button" @click="creating = true; $nextTick(() => $refs.newName.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
            + {{ __('Neue Projektschablone') }}
        </button>
    </div>

    <form x-show="creating" x-cloak method="POST" action="{{ route('admin.projektschablonen.store') }}" class="rounded-md border border-gray-200 p-3">
        @csrf
        @include('admin.project-templates.partials.fields', ['template' => null])
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" @click="creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Abbrechen') }}
            </button>
            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Speichern') }}
            </button>
        </div>
    </form>

    <div class="space-y-2">
        @forelse ($templates as $template)
            <div class="rounded-md border border-gray-200 p-3 {{ ! $template->active ? 'bg-gray-50' : '' }}" x-data="{}">
                @php
                    $createdByName = $template->createdByUser?->person?->fullName() ?? $template->createdByUser?->name ?? '–';
                    $updatedByName = $template->updatedByUser?->person?->fullName() ?? $template->updatedByUser?->name;
                @endphp
                <form data-row-form x-data="{ dirty: false }" @input="dirty = window.formIsDirty($el)" @submit="dirty = false" method="POST" action="{{ route('admin.projektschablonen.update', $template) }}">
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
                    Speichern-Vorgang (sync auf die Pivot-Tabelle), nicht Teil
                    des Haupt-Formulars oben. Fktgrp-Auswahl noch manuell,
                    Step 3 leitet sie später aus der Workflow-Kopplung ab.
                --}}
                @if ($functionGroups->isEmpty())
                    <p class="mt-3 border-t border-gray-100 pt-2 text-xs text-gray-400">{{ __('Noch keine Funktionsgruppen angelegt.') }}</p>
                @else
                    @php($plannedHours = $template->functionGroups->mapWithKeys(fn ($fg) => [(string) $fg->id => (string) $fg->pivot->planned_hours]))
                    <form
                        data-row-form
                        x-data="{ dirty: false, hours: {{ \Illuminate\Support\Js::from($plannedHours) }} }"
                        @input="dirty = window.formIsDirty($el)"
                        @submit="dirty = false"
                        method="POST"
                        action="{{ route('admin.projektschablonen.funktionsgruppen.update', $template) }}"
                        class="mt-3 border-t border-gray-100 pt-2"
                    >
                        @csrf
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium text-gray-600">{{ __('Stunden je Funktionsgruppe') }}</p>
                            <p class="text-xs text-gray-400">
                                {{ __('Summe') }}: <span x-text="Object.values(hours).reduce((sum, v) => sum + (parseFloat(v) || 0), 0).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })"></span> h
                            </p>
                        </div>
                        <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($functionGroups as $fg)
                                <label class="flex items-center gap-1 text-xs text-gray-600">
                                    <span class="min-w-0 flex-1 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                                    <input type="number" name="hours[{{ $fg->id }}]" x-model="hours['{{ $fg->id }}']" min="0" max="999" step="0.5" placeholder="–" class="w-16 rounded-md border-gray-300 py-0.5 text-xs">
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

                <form method="POST" action="{{ route('admin.projektschablonen.destroy', $template) }}" x-ref="deleteForm" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="mt-1 flex justify-end">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, { message: {{ \Illuminate\Support\Js::from(__('Diese Projektschablone wirklich endgültig löschen?')) }} })"
                        class="rounded-md border border-red-300 px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>
        @empty
            <p class="px-2 py-1 text-xs text-gray-400">{{ __('Noch keine Projektschablonen angelegt.') }}</p>
        @endforelse
    </div>
</div>
