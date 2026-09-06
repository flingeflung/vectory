<x-admin-layout>
    @if (session('status') === 'rechte-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div class="flex flex-1 min-h-0 gap-4">
        {{-- Links: WEN - erst die Rechte-Sets, dann alle Personen. Zwei
             getrennte Boxen mit je eigenem fixen Header, nur die Listen
             selbst scrollen - so bleibt "+ Neu" bzw. Suche/Toggle immer
             sichtbar, egal wie lang die jeweilige Liste ist. --}}
        <div
            x-data="{
                search: '',
                showInactive: false,
                newSet: false,
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
            {{-- Rechte-Sets: bewusst etwas höher als nötig (Wunsch: mehr auf
                 einen Blick) und per Drag&Drop sortierbar (x-sort, gleiches
                 Muster wie beim Dashboard-Kachel-Layout) - rein visuelle
                 Rangordnung (z.B. "wer hat absteigend die meisten Rechte"),
                 kein fachlicher Effekt. --}}
            <div class="flex h-64 shrink-0 flex-col rounded-lg border border-gray-200 bg-white">
                <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                    <span class="text-xs font-semibold text-gray-500">{{ __('Rechte-Sets') }}</span>
                    <button type="button" @click="newSet = !newSet" class="text-xs text-indigo-600 hover:text-indigo-800">
                        + {{ __('Neu') }}
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                    <form x-show="newSet" x-cloak method="POST" action="{{ route('admin.rechte.sets.store') }}" class="mb-2 space-y-1.5 rounded border border-gray-200 p-2">
                        <select name="base_id" class="w-full rounded-md border-gray-300 text-xs" required>
                            <option value="">{{ __('Auf Basis von…') }}</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="name" placeholder="{{ __('Name des neuen Sets') }}" class="w-full rounded-md border-gray-300 text-xs" required>
                        @csrf
                        <button type="submit" class="w-full rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700">
                            {{ __('Anlegen') }}
                        </button>
                    </form>

                    <div
                        x-data="{
                            async saveOrder() {
                                const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                await fetch({{ \Illuminate\Support\Js::from(route('admin.rechte.sets.reorder')) }}, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                    body: JSON.stringify({ sets: ids }),
                                });
                            },
                        }"
                        x-sort="saveOrder()"
                    >
                        @foreach ($templates as $template)
                            <div x-sort:item="{{ $template->id }}" class="flex items-center gap-1 rounded {{ $selectedTemplate?->id === $template->id ? 'bg-indigo-50' : 'hover:bg-gray-50' }}">
                                <span x-sort:handle class="cursor-move px-1 text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                                <a
                                    href="{{ route('admin.rechte', ['set' => $template->id]) }}"
                                    @if ($selectedTemplate?->id === $template->id) data-selected @endif
                                    class="flex-1 py-1 {{ $selectedTemplate?->id === $template->id ? 'font-medium text-indigo-700' : 'text-gray-700' }}"
                                >
                                    {{ $template->name }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Personen: nimmt den restlichen Platz. --}}
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

                    @if ($selectedTemplate)
                        <button
                            type="submit"
                            form="bulk-assign-form"
                            x-show="dirty"
                            x-cloak
                            class="w-full rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-white hover:bg-gray-700"
                        >
                            {{ __('Speichern') }}
                        </button>
                    @endif
                </div>
                <div x-ref="personList" class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                @if ($selectedTemplate)
                    {{-- Bulk-Zuordnung: nur unzugeordnete Personen sind hier
                         anklickbar (leer). Wer schon einem Set angehört -
                         diesem oder einem anderen - ist gesperrt, damit man
                         beim schnellen Durchklicken niemanden versehentlich
                         "klaut". Verschieben einer Person in ein anderes Set
                         läuft bewusst über ihre Einzelseite (siehe unten). --}}
                    <form
                        id="bulk-assign-form"
                        method="POST"
                        action="{{ route('admin.rechte.sets.assign-people', $selectedTemplate) }}"
                        @change="dirty = true"
                        @submit="dirty = false"
                        x-init="window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } })"
                    >
                        @csrf
                        @foreach ($people as $person)
                            @php $isAssigned = $person->permission_template_id !== null; @endphp
                            <div
                                data-person-row
                                data-department-name="{{ $person->department?->name }}"
                                data-sort-index="{{ $loop->index }}"
                                x-show="(showInactive || {{ $person->active || $selectedPerson?->id === $person->id ? 'true' : 'false' }}) && (!search || {{ \Illuminate\Support\Js::from(mb_strtolower($person->fullName())) }}.includes(search.toLowerCase()))"
                                class="flex items-center gap-1.5 rounded px-2 py-1 {{ $isAssigned ? 'opacity-50' : 'hover:bg-gray-50' }}"
                            >
                                <input
                                    type="checkbox"
                                    name="person_ids[]"
                                    value="{{ $person->id }}"
                                    class="rounded border-gray-300"
                                    @checked($person->permission_template_id === $selectedTemplate->id)
                                    @disabled($isAssigned)
                                >
                                <a
                                    href="{{ route('admin.rechte', ['person' => $person->id]) }}"
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
                            href="{{ route('admin.rechte', ['person' => $person->id]) }}"
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

        {{-- Rechts: WAS - entweder die Rechte-Checkliste eines Sets (plus wer
             es gerade erbt, damit die Tragweite jeder Änderung sichtbar ist),
             oder das Set einer Person. --}}
        <div x-data="{ search: '' }" class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
            @if ($selectedTemplate)
                <div class="shrink-0 flex items-center justify-between gap-3 border-b border-gray-100 p-3">
                    <div class="text-sm font-medium text-gray-900">
                        <input
                            type="text"
                            name="name"
                            form="set-form"
                            value="{{ $selectedTemplate->name }}"
                            required
                            class="rounded-md border-gray-300 py-0.5 text-sm font-medium text-gray-900"
                        >
                        <span class="ml-1 text-xs font-normal text-gray-400">
                            {{ __('Gilt für :count Person(en) - Änderungen wirken sofort für alle.', ['count' => $templatePeople->count()]) }}
                        </span>
                    </div>
                    <input
                        type="search"
                        x-model="search"
                        placeholder="{{ __('Filter (Rechte)') }}"
                        class="w-48 rounded-md border-gray-300 py-1 text-sm"
                    >
                </div>

                <div class="flex flex-1 min-h-0">
                    <form
                        id="set-form"
                        method="POST"
                        action="{{ route('admin.rechte.sets.update', $selectedTemplate) }}"
                        class="flex flex-1 min-h-0 flex-col border-r border-gray-100"
                    >
                        @csrf
                        <div class="flex-1 min-h-0 overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($permissions as $permission)
                                        <tr x-show="!search || {{ \Illuminate\Support\Js::from(mb_strtolower($permission->label)) }}.includes(search.toLowerCase())">
                                            <td class="w-10 px-3 py-2 text-center">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $permission->id }}"
                                                    class="rounded border-gray-300"
                                                    @checked($grantedPermissionIds->contains($permission->id))
                                                >
                                            </td>
                                            <td class="px-3 py-2 text-gray-700" title="{{ $permission->key }}">{{ $permission->label }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="shrink-0 border-t border-gray-100 p-3">
                            <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                                {{ __('Speichern') }}
                            </button>
                        </div>
                    </form>

                    <div class="flex w-64 shrink-0 flex-col">
                        <div class="shrink-0 border-b border-gray-100 p-3 text-xs font-semibold text-gray-500">
                            {{ __('Personen mit diesem Set') }} ({{ $templatePeople->count() }})
                        </div>
                        <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm">
                            @forelse ($templatePeople as $person)
                                <div class="px-1 py-1 {{ $person->active ? 'text-gray-700' : 'text-gray-400' }}">
                                    {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-department-tag :person="$person" />
                                </div>
                            @empty
                                <div class="px-1 py-1 text-gray-400">{{ __('Niemand.') }}</div>
                            @endforelse
                        </div>
                        <div class="shrink-0 border-t border-gray-100 p-3">
                            <form
                                method="POST"
                                action="{{ route('admin.rechte.sets.destroy', $selectedTemplate) }}"
                                class="space-y-1.5"
                                x-data="{ async confirmAndSubmit(e) { if (await window.confirmDialog({ title: '{{ __('Set löschen') }}', message: '{{ __('Set wirklich löschen?') }}', confirmLabel: '{{ __('Löschen') }}' })) { e.target.submit(); } } }"
                                @submit.prevent="confirmAndSubmit($event)"
                            >
                                @if ($templatePeople->isNotEmpty())
                                    <div class="text-xs text-gray-400">
                                        {{ __('Zum Löschen eines Sets müssen die zugewiesenen Personen in ein anderes Set übernommen werden.') }}
                                    </div>
                                    <select name="reassign_to" class="w-full rounded-md border-gray-300 text-xs" required>
                                        <option value="">{{ __('Personen übernehmen in…') }}</option>
                                        @foreach ($templates as $template)
                                            @if ($template->id !== $selectedTemplate->id)
                                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                @endif
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                    {{ __('Set löschen') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @elseif ($selectedPerson)
                <div class="shrink-0 border-b border-gray-100 p-3 text-sm font-medium text-gray-900">
                    {{ $selectedPerson->fullName() }} <x-department-tag :person="$selectedPerson" />
                </div>

                <form method="POST" action="{{ route('admin.rechte.personen.update', $selectedPerson) }}" class="flex-1 min-h-0 overflow-y-auto p-3">
                    @csrf
                    <div class="mb-3 text-xs text-gray-500">{{ __('Rechte-Set auswählen - bestimmt die Rechte dieser Person vollständig.') }}</div>
                    <div class="space-y-1">
                        @foreach ($templates as $template)
                            <label class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50">
                                <input type="radio" name="permission_template_id" value="{{ $template->id }}" @checked($selectedPerson->permission_template_id === $template->id)>
                                <span class="text-sm text-gray-700">{{ $template->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="mt-3 rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                        {{ __('Speichern') }}
                    </button>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-gray-400">
                    {{ __('Links ein Rechte-Set oder eine Person auswählen.') }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
