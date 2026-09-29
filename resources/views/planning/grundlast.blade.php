<x-planning-layout>
    <script>
        window.__baseLoadDirtyForms = new Set();
        window.adminPageIsDirty = () => window.__baseLoadDirtyForms.size > 0;
    </script>

    @if (session('status'))
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
            @switch(session('status'))
                @case('base-load-copied') {{ __('Grundlast aus dem Vorjahr kopiert.') }} @break
                @case('base-load-deleted') {{ __('Grundlast gelöscht.') }} @break
                @default {{ __('Gespeichert.') }}
            @endswitch
        </x-flash-message>
    @endif

    @if ($errors->any())
        <div class="mb-3 shrink-0 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-3 shrink-0 space-y-2" x-data="{ creating: {{ old('_form') === 'create' ? 'true' : 'false' }} }">
        <p class="text-xs text-gray-500">
            {{ __('Kunde') }}:
            <span class="font-medium text-gray-700">{{ $tenant?->short_name ?: ($tenant?->name ?? '–') }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <form method="GET" action="{{ route('planung.grundlast') }}" class="flex items-center gap-2">
                <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                    <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($years as $itemYear)
                            <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                        @endforeach
                    </select>
                </label>
            </form>

            <button type="button" x-show="!creating" @click="creating = true; $nextTick(() => $refs.newName.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                + {{ __('Grundlast anlegen') }}
            </button>

            @if ($previousYearCount > 0)
                <form method="POST" action="{{ route('planung.grundlast.copy-previous') }}">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <button type="submit" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Aus :year kopieren', ['year' => $year - 1]) }}
                    </button>
                </form>
            @endif
        </div>

        <form
            x-show="creating"
            x-cloak
            method="POST"
            action="{{ route('planung.grundlast.store') }}"
            x-data="{ dirty: {{ old('_form') === 'create' && $errors->any() ? 'true' : 'false' }} }"
            x-init="if (dirty) window.__baseLoadDirtyForms.add($el)"
            @input="dirty = window.formIsDirty($el, window.__baseLoadDirtyForms)"
            @submit="window.__baseLoadDirtyForms.delete($el)"
            class="grid grid-cols-1 items-end gap-2 rounded-md border border-gray-200 bg-white p-3 md:grid-cols-[minmax(12rem,2fr)_minmax(10rem,1fr)_8rem_10rem_10rem_auto_auto]"
        >
            @csrf
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="year" value="{{ $year }}">
            <label class="text-xs text-gray-500">{{ __('Bezeichnung') }}
                <input x-ref="newName" type="text" name="name" value="{{ old('name') }}" required maxlength="255" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
            </label>
            <label class="text-xs text-gray-500">{{ __('Berechnungsart') }}
                <select name="calculation_type" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    <option value="weekly" @selected(old('calculation_type') === 'weekly')>{{ __('Stunden/Woche') }}</option>
                    <option value="yearly" @selected(old('calculation_type') === 'yearly')>{{ __('Stunden/Jahr') }}</option>
                </select>
            </label>
            <label class="text-xs text-gray-500">{{ __('Wert') }}
                <input type="number" name="value" value="{{ old('value') }}" min="0" step="0.25" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-right text-sm tabular-nums">
            </label>
            <label class="text-xs text-gray-500">{{ __('Gültig von') }}
                <input type="date" name="valid_from" value="{{ old('valid_from', $year.'-01-01') }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
            </label>
            <label class="text-xs text-gray-500">{{ __('Gültig bis') }}
                <input type="date" name="valid_to" value="{{ old('valid_to', $year.'-12-31') }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
            </label>
            <button type="button" @click="window.__baseLoadDirtyForms.delete($el.closest('form')); $el.closest('form').reset(); dirty = false; creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
            <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
        </form>
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3">
        @forelse ($baseLoads as $baseLoad)
            <div class="flex items-end gap-2 rounded-md border border-gray-200 p-2" x-data>
                <form
                    method="POST"
                    action="{{ route('planung.grundlast.update', $baseLoad) }}"
                    data-row-form
                    x-data="{ dirty: false }"
                    @input="dirty = window.formIsDirty($el, window.__baseLoadDirtyForms)"
                    @submit="window.__baseLoadDirtyForms.delete($el)"
                    class="grid min-w-0 flex-1 grid-cols-1 items-end gap-2 md:grid-cols-[minmax(12rem,2fr)_minmax(10rem,1fr)_8rem_10rem_10rem_auto]"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="update">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <label class="text-xs text-gray-500">{{ __('Bezeichnung') }}
                        <input type="text" name="name" value="{{ $baseLoad->name }}" required maxlength="255" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    </label>
                    <label class="text-xs text-gray-500">{{ __('Berechnungsart') }}
                        <select name="calculation_type" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                            <option value="weekly" @selected($baseLoad->calculation_type === 'weekly')>{{ __('Stunden/Woche') }}</option>
                            <option value="yearly" @selected($baseLoad->calculation_type === 'yearly')>{{ __('Stunden/Jahr') }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-500">{{ __('Wert') }}
                        <input type="number" name="value" value="{{ number_format((float) $baseLoad->value, 2, '.', '') }}" min="0" step="0.25" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-right text-sm tabular-nums">
                    </label>
                    <label class="text-xs text-gray-500">{{ __('Gültig von') }}
                        <input type="date" name="valid_from" value="{{ $baseLoad->valid_from->format('Y-m-d') }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    </label>
                    <label class="text-xs text-gray-500">{{ __('Gültig bis') }}
                        <input type="date" name="valid_to" value="{{ $baseLoad->valid_to->format('Y-m-d') }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    </label>
                    <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>

                <form method="POST" action="{{ route('planung.grundlast.destroy', $baseLoad) }}" x-ref="deleteForm{{ $baseLoad->id }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <button
                    type="button"
                    @click="if (await window.confirmDialog({ title: {{ \Illuminate\Support\Js::from(__('Grundlast löschen')) }}, message: {{ \Illuminate\Support\Js::from(__('Diesen Grundlast-Datensatz wirklich endgültig löschen?')) }}, confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }} })) $refs.deleteForm{{ $baseLoad->id }}.submit()"
                    class="rounded-md border border-red-300 px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                >{{ __('Löschen') }}</button>
            </div>
        @empty
            <div class="p-4 text-center text-sm text-gray-400">{{ __('Für :year ist noch keine Grundlast angelegt.', ['year' => $year]) }}</div>
        @endforelse
    </div>
</x-planning-layout>
