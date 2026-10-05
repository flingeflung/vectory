<x-planning-layout>
    <script>
        window.__personBaseLoadDirtyForms = new Set();
        window.adminPageIsDirty = () => window.__personBaseLoadDirtyForms.size > 0;
    </script>

    @if (session('status'))
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
            @switch(session('status'))
                @case('person-base-load-deleted') {{ __('Datensatz gelöscht.') }} @break
                @case('person-base-load-inherited') {{ __('Grundlastbasis übernommen. Neue Basis-Datensätze wurden ergänzt.') }} @break
                @default {{ __('Gespeichert.') }}
            @endswitch
        </x-flash-message>
    @endif

    @if ($errors->any())
        <div class="mb-3 shrink-0 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mb-3 shrink-0 space-y-2">
        <div class="flex flex-wrap items-start gap-3 text-sm">
            <form method="GET" action="{{ route('planung.grundlast-person') }}" class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                    <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($years as $itemYear)
                            <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-gray-700">{{ __('Person') }}
                    <select name="person" onchange="this.form.submit()" class="min-w-48 rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($people as $itemPerson)
                            <option value="{{ $itemPerson->id }}" @selected($person?->id === $itemPerson->id)>{{ $itemPerson->fullName() }}</option>
                        @endforeach
                    </select>
                </label>
            </form>

            @if ($person && $missingBaseLoadCount > 0)
                <form method="POST" action="{{ route('planung.grundlast-person.inherit') }}">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="person" value="{{ $person->id }}">
                    <button type="submit" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Grundlastbasis übernehmen') }}
                    </button>
                </form>
            @endif

            <div class="ml-auto space-y-1">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-gray-600">
                        {{ __('Summe Std/Woche') }}
                        <input type="text" readonly value="{{ number_format($weeklyTotal, 2, ',', '.') }}" class="w-24 rounded-md border-gray-300 bg-gray-50 py-1 text-right text-sm font-medium tabular-nums text-gray-800">
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-600">
                        {{ __('Summe Std/Jahr') }}
                        <input type="text" readonly value="{{ number_format($yearlyTotal, 2, ',', '.') }}" class="w-24 rounded-md border-gray-300 bg-gray-50 py-1 text-right text-sm font-medium tabular-nums text-gray-800">
                    </label>
                </div>
                <p class="text-right text-xs text-gray-400">{{ __('Berechnung mit :count Standard-Wochen pro Jahr.', ['count' => $standardWeeks]) }}</p>
            </div>
        </div>

        @if ($deletedBaseLoadCount > 0)
            @php
                $orphanNames = $personBaseLoads->whereNull('planning_base_load_id')->pluck('name')->map(fn ($name) => '„'.$name.'“')->join(', ');
            @endphp
            <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ trans_choice('{1} Der individuelle Datensatz :names stammt aus einer inzwischen gelöschten Grundlastbasis. Löschen Sie ihn bei Bedarf oder setzen Sie den Wert auf 0.|[2,*] Die individuellen Datensätze :names stammen aus inzwischen gelöschten Grundlastbasen. Löschen Sie sie bei Bedarf oder setzen Sie die Werte auf 0.', $deletedBaseLoadCount, ['names' => $orphanNames]) }}
            </div>
        @endif
    </div>

    <div class="min-h-0 flex-1 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3">
        @forelse ($personBaseLoads as $personBaseLoad)
            <form
                method="POST"
                action="{{ route('planung.grundlast-person.update', $personBaseLoad) }}"
                x-data="{ dirty: false }"
                @input="dirty = window.formIsDirty($el, window.__personBaseLoadDirtyForms)"
                @submit.prevent="window.submitPersonBaseLoadForm($event)"
                class="grid grid-cols-1 items-start gap-2 rounded-md border border-gray-200 p-2 md:grid-cols-[minmax(12rem,2fr)_minmax(10rem,1fr)_8rem_10rem_10rem_9.5rem]"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="person" value="{{ $person->id }}">
                <label class="text-xs text-gray-500">{{ __('Bezeichnung') }}
                    <input type="text" name="name" value="{{ $personBaseLoad->name }}" required maxlength="255" class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                    @if ($personBaseLoad->planning_base_load_id === null)
                        <span class="mt-0.5 block text-xs text-amber-700">{{ __('Basis-Datensatz wurde gelöscht') }}</span>
                    @endif
                </label>
                <label class="text-xs text-gray-500">{{ __('Berechnungsart') }}
                    <select name="calculation_type" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                        <option value="weekly" @selected($personBaseLoad->calculation_type === 'weekly')>{{ __('Stunden/Woche') }}</option>
                        <option value="yearly" @selected($personBaseLoad->calculation_type === 'yearly')>{{ __('Stunden/Jahr') }}</option>
                    </select>
                </label>
                <label class="text-xs text-gray-500">{{ __('Wert') }}
                    <input type="number" name="value" value="{{ number_format((float) $personBaseLoad->value, 2, '.', '') }}" min="0" step="0.25" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-right text-sm tabular-nums">
                </label>
                <label class="text-xs text-gray-500">{{ __('Gültig von') }}
                    <input type="date" name="valid_from" value="{{ $personBaseLoad->valid_from->format('Y-m-d') }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                </label>
                <label class="text-xs text-gray-500">{{ __('Gültig bis') }}
                    <input type="date" name="valid_to" value="{{ $personBaseLoad->valid_to->format('Y-m-d') }}" min="{{ $year }}-01-01" max="{{ $year }}-12-31" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
                </label>
                <div class="flex items-center justify-end gap-1.5 md:mt-[1.125rem]">
                    <button type="submit" :class="dirty ? '' : 'invisible'" :tabindex="dirty ? 0 : -1" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                    <button
                        type="button"
                        title="{{ __('Datensatz löschen') }}"
                        aria-label="{{ __('Datensatz löschen') }}"
                        @click="window.deleteWithConfirm(document.getElementById('delete-person-base-load-{{ $personBaseLoad->id }}'), {
                            title: {{ \Illuminate\Support\Js::from(__('Datensatz löschen?')) }},
                            message: {{ \Illuminate\Support\Js::from(__('Der Datensatz „:name“ von :person wird gelöscht. Das lässt sich nicht rückgängig machen.', ['name' => $personBaseLoad->name, 'person' => $person->fullName()])) }},
                            confirmLabel: {{ \Illuminate\Support\Js::from(__('Löschen')) }},
                        })"
                        class="rounded-md border border-gray-300 bg-btn-secondary p-1.5 text-gray-500 hover:bg-red-50 hover:text-red-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                    </button>
                </div>
            </form>
            <form method="POST" action="{{ route('planung.grundlast-person.destroy', $personBaseLoad) }}" id="delete-person-base-load-{{ $personBaseLoad->id }}" class="hidden">
                @csrf
                @method('DELETE')
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="person" value="{{ $person->id }}">
            </form>
        @empty
            <div class="p-4 text-center text-sm text-gray-400">
                @if ($person)
                    {!! __('Für diese Person sind noch keine individuellen :baseload-Datensätze vorhanden.', ['baseload' => \App\Models\GlossaryTerm::link('Grundlast')]) !!}
                @else
                    {{ __('Keine Person für die Ressourcenplanung vorhanden.') }}
                @endif
            </div>
        @endforelse
    </div>

    <div id="person-base-load-saving" class="fixed inset-0 z-[100] hidden cursor-wait items-center justify-center bg-gray-900/20" role="status" aria-live="polite">
        <div class="flex flex-col items-center gap-3 rounded-lg bg-white px-8 py-6 shadow-lg">
            <x-loading-spinner class="h-10 w-10 text-gray-600" />
            <span class="text-sm text-gray-600">{{ __('Speichert…') }}</span>
        </div>
    </div>

    <script>
        window.submitPersonBaseLoadForm = (event) => {
            const form = event.target;
            window.__personBaseLoadDirtyForms.delete(form);
            document.getElementById('person-base-load-saving')?.classList.replace('hidden', 'flex');
            requestAnimationFrame(() => requestAnimationFrame(() => form.submit()));
        };
    </script>
</x-planning-layout>
