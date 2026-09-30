<x-admin-layout>
    <script>
        window.__holidayDirtyForms = new Set();
        window.adminPageIsDirty = () => window.__holidayDirtyForms.size > 0;
    </script>

    @if (session('status'))
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
            @switch(session('status'))
                @case('holiday-deleted') {{ __('Feiertag gelöscht.') }} @break
                @case('holidays-imported')
                    {{ __('Von :name wurden :copied Feiertage importiert; :skipped bereits vorhandene Feiertage wurden übersprungen.', [
                        'name' => session('import_source_name'),
                        'copied' => session('holidays_copied'),
                        'skipped' => session('holidays_skipped'),
                    ]) }}
                    @break
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

            @if ($otherTenants->isNotEmpty())
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'feiertage-uebernehmen' }))"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >{{ __('Von anderem Kunden importieren') }}</button>
            @endif
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

    @if ($otherTenants->isNotEmpty())
        <x-modal name="feiertage-uebernehmen" max-width="sm" :draggable="true">
            <form
                method="POST"
                action="{{ route('admin.feiertage.uebernehmen') }}"
                x-data="{ sourceTenantId: '' }"
                x-on:open-modal.window="if ($event.detail === 'feiertage-uebernehmen') $nextTick(() => $refs.sourceTenant.focus())"
            >
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <div data-drag-handle class="flex cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Feiertage importieren') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'feiertage-uebernehmen' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-4 text-sm">
                    <label class="block text-xs text-gray-500">{{ __('Kunde, von dem importiert werden soll') }}</label>
                    <select x-ref="sourceTenant" x-model="sourceTenantId" name="source_tenant_id" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– bitte wählen –') }}</option>
                        @foreach ($otherTenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-400">{{ __('Alle Feiertage des gewählten Kunden werden kopiert. Feiertage mit gleichem Datum und gleicher Bezeichnung werden übersprungen; vorhandene Datensätze werden nicht verändert.') }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 p-3">
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'feiertage-uebernehmen' }))"
                        class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >{{ __('Abbrechen') }}</button>
                    <button type="submit" :disabled="!sourceTenantId" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">
                        {{ __('Importieren') }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    <p class="mb-1 shrink-0 text-xs text-gray-500">
        {{ trans_choice('{1} :count gespeicherter Feiertag für dieses Jahr, davon :active als aktiv markiert|[2,*] :count gespeicherte Feiertage für dieses Jahr, davon :active als aktiv markiert', $holidays->count(), ['count' => $holidays->count(), 'active' => $holidays->where('active', true)->count()]) }}
    </p>

    <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-gray-50 text-left text-xs text-gray-500">
                <tr class="border-b border-gray-200">
                    <th class="w-8 px-2 py-1.5"><span class="sr-only">{{ __('Bearbeiten') }}</span></th>
                    <th class="w-28 px-2 py-1.5 font-medium">{{ __('Datum') }}</th>
                    <th class="w-24 px-2 py-1.5 font-medium">{{ __('Wochentag') }}</th>
                    <th class="px-2 py-1.5 font-medium">{{ __('Bezeichnung') }}</th>
                    <th class="px-2 py-1.5 font-medium">{{ __('Bemerkungen') }}</th>
                    <th class="w-20 px-2 py-1.5 font-medium">{{ __('Status') }}</th>
                    <th class="w-36 px-2 py-1.5"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($holidays as $holiday)
                    @php
                        $failedUpdate = old('_form') === 'update' && (int) old('_holiday') === $holiday->id;
                        $weekdayName = match ($holiday->weekday) {
                            1 => __('Montag'), 2 => __('Dienstag'), 3 => __('Mittwoch'), 4 => __('Donnerstag'),
                            5 => __('Freitag'), 6 => __('Samstag'), 7 => __('Sonntag'), default => '–',
                        };
                        $formId = 'holiday-form-'.$holiday->id;
                    @endphp
                    <tr
                        class="border-b border-gray-100 last:border-b-0 hover:bg-gray-50 {{ $holiday->active ? '' : 'bg-gray-50 opacity-60 hover:opacity-80' }}"
                        :class="{ 'opacity-100': editing }"
                        x-data="{ editing: {{ $failedUpdate ? 'true' : 'false' }}, dirty: {{ $failedUpdate ? 'true' : 'false' }} }"
                        x-init="if (dirty) window.__holidayDirtyForms.add(document.getElementById('{{ $formId }}'))"
                        @input="dirty = window.formIsDirty(document.getElementById('{{ $formId }}'), window.__holidayDirtyForms)"
                    >
                        <td class="px-2 py-1.5 align-middle">
                            <x-edit-icon-button x-show="!editing" @click="const form = document.getElementById('{{ $formId }}'); form.dataset.dirtyBaseline = window.formSnapshot(form); editing = true; $nextTick(() => $refs.editName.focus())" :title="__('Feiertag bearbeiten')" />
                        </td>
                        <td class="px-2 py-1.5 align-middle tabular-nums">
                            <span x-show="!editing">{{ $holiday->date->format('d.m.Y') }}</span>
                            <input x-show="editing" x-cloak form="{{ $formId }}" type="date" name="date" value="{{ $failedUpdate ? old('date') : $holiday->date->format('Y-m-d') }}" required class="w-36 rounded-md border-gray-300 py-1 text-sm">
                        </td>
                        <td class="px-2 py-1.5 align-middle text-gray-500">{{ $weekdayName }}</td>
                        <td class="px-2 py-1.5 align-middle">
                            <span x-show="!editing">{{ $holiday->name }}</span>
                            <input x-ref="editName" x-show="editing" x-cloak form="{{ $formId }}" type="text" name="name" value="{{ $failedUpdate ? old('name') : $holiday->name }}" required maxlength="255" class="w-full min-w-48 rounded-md border-gray-300 py-1 text-sm">
                        </td>
                        <td class="px-2 py-1.5 align-middle text-gray-500">
                            <span x-show="!editing">{{ $holiday->remarks ?: '–' }}</span>
                            <input x-show="editing" x-cloak form="{{ $formId }}" type="text" name="remarks" value="{{ $failedUpdate ? old('remarks') : $holiday->remarks }}" maxlength="5000" class="w-full min-w-64 rounded-md border-gray-300 py-1 text-sm">
                        </td>
                        <td class="px-2 py-1.5 align-middle">
                            <span x-show="!editing" class="{{ $holiday->active ? 'text-gray-600' : 'text-red-600' }}">{{ $holiday->active ? __('Aktiv') : __('Inaktiv') }}</span>
                            <label x-show="editing" x-cloak class="flex items-center gap-2 whitespace-nowrap text-gray-700">
                                <input form="{{ $formId }}" type="hidden" name="active" value="0">
                                <input form="{{ $formId }}" type="checkbox" name="active" value="1" @checked($failedUpdate ? old('active') : $holiday->active) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                {{ __('Aktiv') }}
                            </label>
                        </td>
                        <td class="px-2 py-1.5 align-middle">
                            <div x-show="editing" x-cloak class="flex items-center justify-end gap-2">
                                <form id="{{ $formId }}" method="POST" action="{{ route('admin.feiertage.update', $holiday) }}" @submit="window.__holidayDirtyForms.delete($el)">
                                    @csrf
                                    <input type="hidden" name="_form" value="update">
                                    <input type="hidden" name="_holiday" value="{{ $holiday->id }}">
                                </form>
                                <button type="button" @click="const form = document.getElementById('{{ $formId }}'); window.__holidayDirtyForms.delete(form); form.reset(); dirty = false; editing = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                                <button form="{{ $formId }}" type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                                <form method="POST" action="{{ route('admin.feiertage.destroy', $holiday) }}" x-ref="deleteForm{{ $holiday->id }}" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <button type="button" @click="if (await window.confirmDialog({ title: {{ \Illuminate\Support\Js::from(__('Feiertag löschen')) }}, message: {{ \Illuminate\Support\Js::from(__('Diesen Feiertag wirklich endgültig löschen?')) }}, confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }} })) $refs.deleteForm{{ $holiday->id }}.submit()" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Löschen') }}</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-sm text-gray-400">{{ __('Für :year sind noch keine Feiertage angelegt.', ['year' => $year]) }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
