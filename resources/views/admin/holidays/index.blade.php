<x-admin-layout>
    <script>
        window.__holidayDirtyForms = new Set();
        window.adminPageIsDirty = () => window.__holidayDirtyForms.size > 0;
    </script>

    @if (session('status'))
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
            @switch(session('status'))
                @case('holiday-deleted') {{ __('Feiertag gelöscht.') }} @break
                @default {{ __('Gespeichert.') }}
            @endswitch
        </x-flash-message>
    @endif

    @if ($errors->any())
        <div class="mb-3 shrink-0 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div
        class="mb-3 shrink-0 space-y-2"
        x-data="{ creating: {{ old('_form') === 'create' ? 'true' : 'false' }}, createDirty: {{ old('_form') === 'create' && $errors->any() ? 'true' : 'false' }} }"
    >
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <form method="GET" action="{{ route('admin.feiertage') }}" class="flex items-center gap-2">
                <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                    <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @forelse ($years as $itemYear)
                            <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                        @empty
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforelse
                    </select>
                </label>
            </form>

            <button
                type="button"
                x-show="!creating"
                @click="creating = true; $nextTick(() => $refs.newName.focus())"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >+ {{ __('Feiertag anlegen') }}</button>
        </div>

        <form
            x-show="creating"
            x-cloak
            method="POST"
            action="{{ route('admin.feiertage.store') }}"
            x-init="if (createDirty) window.__holidayDirtyForms.add($el)"
            @input="createDirty = window.formIsDirty($el, window.__holidayDirtyForms)"
            @submit="window.__holidayDirtyForms.delete($el)"
            class="grid grid-cols-1 items-end gap-2 rounded-md border border-gray-200 bg-white p-3 md:grid-cols-[minmax(12rem,2fr)_10rem_minmax(14rem,3fr)_auto_auto_auto]"
        >
            @csrf
            <input type="hidden" name="_form" value="create">
            <label class="text-xs text-gray-500">{{ __('Bezeichnung') }}
                <input x-ref="newName" type="text" name="name" value="{{ old('name') }}" required maxlength="255" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
            </label>
            <label class="text-xs text-gray-500">{{ __('Datum') }}
                <input type="date" name="date" value="{{ old('date', $year.'-01-01') }}" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
            </label>
            <label class="text-xs text-gray-500">{{ __('Bemerkungen') }}
                <input type="text" name="remarks" value="{{ old('remarks') }}" maxlength="5000" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
            </label>
            <label class="flex items-center gap-2 pb-1.5 text-sm text-gray-700">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked(old('active', true)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                {{ __('Aktiv') }}
            </label>
            <button type="button" @click="window.__holidayDirtyForms.delete($el.closest('form')); $el.closest('form').reset(); createDirty = false; creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
            <button type="submit" x-show="createDirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
        </form>
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3">
        @forelse ($holidays as $holiday)
            <div class="flex items-end gap-2 rounded-md border border-gray-200 p-2">
                <form
                    method="POST"
                    action="{{ route('admin.feiertage.update', $holiday) }}"
                    x-data="{ dirty: false }"
                    @input="dirty = window.formIsDirty($el, window.__holidayDirtyForms)"
                    @submit="window.__holidayDirtyForms.delete($el)"
                    class="grid min-w-0 flex-1 grid-cols-1 items-end gap-2 md:grid-cols-[minmax(12rem,2fr)_10rem_minmax(14rem,3fr)_auto_auto]"
                >
                    @csrf
                    <label class="text-xs text-gray-500">{{ __('Bezeichnung') }}
                        <input type="text" name="name" value="{{ $holiday->name }}" required maxlength="255" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    </label>
                    <label class="text-xs text-gray-500">{{ __('Datum') }}
                        <input type="date" name="date" value="{{ $holiday->date->format('Y-m-d') }}" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    </label>
                    <label class="text-xs text-gray-500">{{ __('Bemerkungen') }}
                        <input type="text" name="remarks" value="{{ $holiday->remarks }}" maxlength="5000" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    </label>
                    <label class="flex items-center gap-2 pb-1.5 text-sm text-gray-700">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" @checked($holiday->active) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        {{ __('Aktiv') }}
                    </label>
                    <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>

                <form method="POST" action="{{ route('admin.feiertage.destroy', $holiday) }}" x-ref="deleteForm{{ $holiday->id }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <button
                    type="button"
                    @click="if (await window.confirmDialog({ title: {{ \Illuminate\Support\Js::from(__('Feiertag löschen')) }}, message: {{ \Illuminate\Support\Js::from(__('Diesen Feiertag wirklich endgültig löschen?')) }}, confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }} })) $refs.deleteForm{{ $holiday->id }}.submit()"
                    class="rounded-md border border-red-300 px-2 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                >{{ __('Löschen') }}</button>
            </div>
        @empty
            <div class="p-4 text-center text-sm text-gray-400">{{ __('Für :year sind noch keine Feiertage angelegt.', ['year' => $year]) }}</div>
        @endforelse
    </div>
</x-admin-layout>
