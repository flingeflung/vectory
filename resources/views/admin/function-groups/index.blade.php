<x-admin-layout>
    @if (session('status') === 'function-groups-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div class="flex flex-1 min-h-0 gap-4">
        {{-- Links: WEN - erst die Funktionsgruppen, dann alle Personen.
             Zwei getrennte Boxen mit je eigenem fixen Header, nur die Listen
             selbst scrollen (siehe CLAUDE.md-Konvention). Bewusst KEIN
             Drag&Drop hier (anders als bei den Rechte-Sets) - für
             Admin-/Stammdatenlisten ist alphabetisch klarer, siehe
             "Minor confirmed decisions" im Roadmap-Memory. --}}
        <div
            x-data="{
                search: '',
                showInactive: false,
                newGroup: false,
                dirty: false,
                sortMode: 'alpha',
                allDepartments: @js($departments),
                peopleData: @js($people->map(fn ($person) => ['id' => $person->id, 'name' => mb_strtolower($person->fullName()), 'active' => $person->active])),
                get visibleCount() {
                    return this.peopleData.filter(p => (this.showInactive || p.active || p.id === {{ $selectedPerson?->id ?? 'null' }}) && (!this.search || p.name.includes(this.search.toLowerCase()))).length;
                },
                applyGrouping() {
                    window.applyPersonGrouping(this.$refs.personList, this.sortMode, this.allDepartments);
                },
            }"
            class="flex w-72 shrink-0 flex-col gap-3"
        >
            <div class="flex h-64 shrink-0 flex-col rounded-lg border border-gray-200 bg-white">
                <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                    <span class="text-xs font-semibold text-gray-500">{{ __('Funktionsgruppen') }}</span>
                    <button type="button" @click="newGroup = !newGroup" class="text-xs text-indigo-600 hover:text-indigo-800">
                        + {{ __('Neu') }}
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                    <form x-show="newGroup" x-cloak method="POST" action="{{ route('admin.function-groups.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                        <input type="text" name="name" placeholder="{{ __('Name') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                        <input type="text" name="short_name" placeholder="{{ __('Kürzel') }}" maxlength="20" class="w-16 shrink-0 rounded-md border-gray-300 text-xs" required>
                        @csrf
                        <button type="submit" class="shrink-0 rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">
                            {{ __('Anlegen') }}
                        </button>
                    </form>

                    @foreach ($groups as $group)
                        <a
                            href="{{ route('admin.function-groups', ['gruppe' => $group->id]) }}"
                            @if ($selectedGroup?->id === $group->id) data-selected @endif
                            class="flex items-center gap-1.5 rounded px-2 py-1 {{ $selectedGroup?->id === $group->id ? 'bg-indigo-50 font-medium text-indigo-700' : ($group->active ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-400 hover:bg-gray-50') }}"
                        >
                            <span class="flex-1">{{ $group->name }}{{ ! $group->active ? ' [i]' : '' }}</span>
                            <span class="text-xs text-gray-400">{{ $group->short_name }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Personen: nimmt den restlichen Platz. Ist eine Gruppe
                 ausgewählt, wird die Liste zur Mitglieder-Zuordnung
                 (Checkbox togglebar, NICHT gesperrt wie beim Rechte-Set -
                 eine Person kann in mehreren Funktionsgruppen sein). --}}
            <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
                <div class="shrink-0 space-y-1.5 border-b border-gray-100 p-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-500">{{ __('Personen') }}</span>
                        <div class="flex items-center gap-1 text-xs">
                            <button
                                type="button"
                                @click="sortMode = 'alpha'; applyGrouping()"
                                :class="sortMode === 'alpha' ? 'bg-gray-800 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'"
                                class="rounded px-1.5 py-0.5"
                            >{{ __('A–Z') }}</button>
                            <button
                                type="button"
                                @click="sortMode = 'department'; applyGrouping()"
                                :class="sortMode === 'department' ? 'bg-gray-800 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'"
                                class="rounded px-1.5 py-0.5"
                            >{{ __('Abteilung') }}</button>
                        </div>
                    </div>
                    <input
                        type="search"
                        x-model="search"
                        @input="$nextTick(() => applyGrouping())"
                        placeholder="{{ __('Schnellsuche (Nachname)') }}"
                        class="w-full rounded-md border-gray-300 py-1 text-xs"
                    >
                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                        <input type="checkbox" x-model="showInactive" @change="$nextTick(() => applyGrouping())" class="rounded border-gray-300">
                        {{ __('Inaktive Personen zeigen') }}
                    </label>
                    <div class="text-xs text-gray-400" x-text="visibleCount + ' ' + (visibleCount === 1 ? {{ \Illuminate\Support\Js::from(__('Person gefunden')) }} : {{ \Illuminate\Support\Js::from(__('Personen gefunden')) }})"></div>

                    @if ($selectedGroup)
                        <button
                            type="submit"
                            form="member-assign-form"
                            x-show="dirty"
                            x-cloak
                            class="w-full rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700"
                        >
                            {{ __('Speichern') }}
                        </button>
                    @endif
                </div>
                <div x-ref="personList" class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                @if ($selectedGroup)
                    <form
                        id="member-assign-form"
                        method="POST"
                        action="{{ route('admin.function-groups.members.update', $selectedGroup) }}"
                        @change="dirty = true"
                        @submit="dirty = false"
                        x-init="window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } })"
                    >
                        @csrf
                        @foreach ($people as $person)
                            <div
                                data-person-row
                                data-department-name="{{ $person->department?->name }}"
                                data-sort-index="{{ $loop->index }}"
                                x-show="(showInactive || {{ $person->active || $selectedPerson?->id === $person->id ? 'true' : 'false' }}) && (!search || {{ \Illuminate\Support\Js::from(mb_strtolower($person->fullName())) }}.includes(search.toLowerCase()))"
                                class="flex items-center gap-1.5 rounded px-2 py-1 hover:bg-gray-50"
                            >
                                <input
                                    type="checkbox"
                                    name="person_ids[]"
                                    value="{{ $person->id }}"
                                    class="rounded border-gray-300"
                                    @checked($groupMemberIds->contains($person->id))
                                >
                                <a
                                    href="{{ route('admin.function-groups', ['person' => $person->id]) }}"
                                    class="flex-1 {{ $person->active ? 'text-gray-700 hover:underline' : 'text-gray-400 hover:underline' }}"
                                >
                                    {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-department-tag :person="$person" />
                                </a>
                            </div>
                        @endforeach
                    </form>
                @else
                    @foreach ($people as $person)
                        <a
                            href="{{ route('admin.function-groups', ['person' => $person->id]) }}"
                            @if ($selectedPerson?->id === $person->id) data-selected @endif
                            data-person-row
                            data-department-name="{{ $person->department?->name }}"
                            data-sort-index="{{ $loop->index }}"
                            x-show="(showInactive || {{ $person->active || $selectedPerson?->id === $person->id ? 'true' : 'false' }}) && (!search || {{ \Illuminate\Support\Js::from(mb_strtolower($person->fullName())) }}.includes(search.toLowerCase()))"
                            class="block rounded px-2 py-1 {{ $selectedPerson?->id === $person->id ? 'bg-indigo-50 font-medium text-indigo-700' : ($person->active ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-400 hover:bg-gray-50') }}"
                        >
                            {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-department-tag :person="$person" />
                        </a>
                    @endforeach
                @endif
                </div>
            </div>
        </div>

        {{-- Rechts: WAS - entweder Stammdaten+Mitgliederzahl einer Gruppe
             (plus Löschen, falls ungenutzt), oder die Gruppen-Mehrfachauswahl
             einer Person. --}}
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
            @if ($selectedGroup)
                @php $inUse = array_sum($usage) > 0; @endphp
                <form
                    method="POST"
                    action="{{ route('admin.function-groups.update', $selectedGroup) }}"
                    class="flex flex-1 min-h-0 flex-col"
                    x-data="{ dirty: false }"
                    @input="dirty = true"
                    @change="dirty = true"
                >
                    @csrf
                    <div class="shrink-0 space-y-2 border-b border-gray-100 p-3">
                        <div class="flex items-center gap-2">
                            <input type="text" name="name" value="{{ $selectedGroup->name }}" required class="flex-1 rounded-md border-gray-300 py-1 text-sm font-medium text-gray-900">
                            <input type="text" name="short_name" value="{{ $selectedGroup->short_name }}" maxlength="20" required class="w-20 rounded-md border-gray-300 py-1 text-sm text-gray-900">
                        </div>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" name="active" value="1" @checked($selectedGroup->active) class="rounded border-gray-300">
                                {{ __('Aktiv') }}
                            </label>
                            <span class="text-xs text-gray-400">
                                {{ trans_choice(':count Mitglied|:count Mitglieder', $groupMemberIds->count(), ['count' => $groupMemberIds->count()]) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-3 text-sm text-gray-500">
                        {{ __('Mitglieder werden links in der Personen-Liste zugeordnet (Checkboxen).') }}
                    </div>

                    <div class="shrink-0 border-t border-gray-100 p-3">
                        <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </form>

                <div class="shrink-0 border-t border-gray-100 p-3">
                    @if ($inUse)
                        <div class="text-xs text-gray-400">
                            {{ __('Wird noch in Projekten/Workflow-Schritten verwendet und kann daher nicht gelöscht werden - stattdessen oben deaktivieren.') }}
                        </div>
                    @else
                        <div x-data="{ confirming: false }">
                            <div x-show="!confirming" class="flex justify-end">
                                <button type="button" @click="confirming = true" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                    {{ __('Löschen') }}
                                </button>
                            </div>
                            <form x-show="confirming" x-cloak method="POST" action="{{ route('admin.function-groups.destroy', $selectedGroup) }}" class="flex items-center justify-end gap-2">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="confirming = false" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                                <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Endgültig löschen') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            @elseif ($selectedPerson)
                <div class="shrink-0 border-b border-gray-100 p-3 text-sm font-medium text-gray-900">
                    {{ $selectedPerson->fullName() }} <x-department-tag :person="$selectedPerson" />
                </div>

                <form method="POST" action="{{ route('admin.function-groups.personen.update', $selectedPerson) }}" class="flex-1 min-h-0 overflow-y-auto p-3">
                    @csrf
                    <div class="mb-3 text-xs text-gray-500">{{ __('Funktionsgruppen dieser Person - Mehrfachauswahl möglich.') }}</div>
                    <div class="space-y-1">
                        @foreach ($groups as $group)
                            <label class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50">
                                <input type="checkbox" name="function_group_ids[]" value="{{ $group->id }}" class="rounded border-gray-300" @checked($personGroupIds->contains($group->id))>
                                <span class="text-sm text-gray-700">{{ $group->name }}{{ ! $group->active ? ' [i]' : '' }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-3 rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                        {{ __('Speichern') }}
                    </button>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-gray-400">
                    {{ __('Links eine Funktionsgruppe oder eine Person auswählen.') }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
