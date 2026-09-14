{{--
    Fragment, per fetch() in #project-group-panel-body geladen (siehe
    project-group-modal.blade.php) - kein <x-modal> hier, das bleibt außen
    bestehen. $project ist null im Übersichts-Modus (mode='uebersicht'),
    gesetzt im Einzelprojekt-Modus (mode='projekt').
--}}
<div>
    <label class="block text-xs text-gray-500">{{ __('Meine Projektgruppen') }}</label>
    {{--
        Ralf (nach Vietto-Vorbild): "wenn man Haken setzt oder entfernt,
        wird das in der im Pulldown angezeigten Gruppe sofort geändert
        (auch die Zahl im Pulldown hinter dem Gruppennamen passt sich
        sofort an)". Serverseitig gerenderte Zahl (data-count) ist der
        Ausgangswert; sobald sich memberIds (Häkchen-Zustand) für die
        AKTUELL gewählte Gruppe ändert, wird nur deren <option>-Text live
        gepatcht - andere Gruppen im Pulldown bleiben unverändert, die
        haben ja keine geladenen memberIds.
    --}}
    <select
        x-model="$store.projectGrouping.groupId"
        @change="onGroupChange()"
        x-init="$watch('$store.projectGrouping.memberIds', () => {
            if (! $store.projectGrouping.groupId) return;
            const opt = $el.querySelector(`option[value='${$store.projectGrouping.groupId}']`);
            if (opt) { opt.textContent = opt.dataset.name + ' (' + $store.projectGrouping.memberIds.length + ')' + opt.dataset.verbundSuffix; }
        })"
        class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
    >
        <option value="">{{ __('– Gruppe auswählen –') }}</option>
        @foreach ($groups as $group)
            {{--
                "Projektverbund" (Ralf, 2026-09-14): Verbund-Gruppen im
                Pulldown klar kennzeichnen - data-verbund-suffix wird auch
                vom obigen live-Patch (Häkchen-Zähler) mit übernommen, sonst
                fiele die Kennzeichnung beim ersten Häkchen-Klick wieder weg.
            --}}
            <option
                value="{{ $group->id }}"
                data-viewers="{{ $group->viewers_count }}"
                data-name="{{ $group->name }}"
                data-verbund-suffix="{{ $group->is_verbund ? ' – '.__('Verbund') : '' }}"
            >{{ $group->name }} ({{ $group->projects_count }}){{ $group->is_verbund ? ' – '.__('Verbund') : '' }}</option>
        @endforeach
    </select>
</div>

<div x-show="$store.projectGrouping.groupId" x-cloak class="mt-3 space-y-1.5 border-t border-gray-100 pt-2 text-xs">
    @if ($project)
        <button type="button" @click="addThisProject()" class="block w-full rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-left font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Dieses Projekt zur gewählten Gruppe hinzufügen') }}
        </button>
        <button type="button" @click="removeThisProject()" class="block w-full rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-left font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Dieses Projekt aus gewählter Gruppe entfernen') }}
        </button>
    @else
        <a :href="showUrl()" class="block rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-left font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Projekte dieser Gruppe anzeigen') }}
        </a>
        <button type="button" @click="addAllFiltered()" class="block w-full rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-left font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Alle angezeigten Projekte zur gewählten Gruppe hinzufügen') }}
        </button>
        <button type="button" @click="removeAllFiltered()" class="block w-full rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-left font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Alle angezeigten Projekte aus gewählter Gruppe entfernen') }}
        </button>
        <button type="button" @click="clearGroup()" class="block w-full rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-left font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Gruppe leeren') }}
        </button>
    @endif

    <div class="flex items-center gap-1 border-t border-gray-100 pt-2">
        <input type="text" x-model="renameValue" x-init="renameValue = {{ \Illuminate\Support\Js::from($groups->pluck('name', 'id')) }}[$store.projectGrouping.groupId] ?? ''" class="min-w-0 flex-1 rounded border-gray-300 py-1 text-xs">
        <button type="button" @click="renameGroup()" title="{{ __('Umbenennen') }}" class="shrink-0 rounded border border-gray-300 bg-btn-secondary p-1 text-gray-500 hover:bg-btn-secondary-hover">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
        </button>
    </div>
    <div class="flex flex-wrap gap-1.5">
        <button type="button" @click="openShare()" class="rounded border border-gray-300 px-2 py-0.5 text-gray-600 hover:bg-gray-50">{{ __('Teilen') }}</button>
        <button type="button" @click="window.openVerbundPanel($store.projectGrouping.groupId)" class="rounded border border-indigo-300 px-2 py-0.5 text-indigo-700 hover:bg-indigo-50">{{ __('Verbund') }}</button>
        <button type="button" @click="leaveGroup()" class="rounded border border-gray-300 px-2 py-0.5 text-gray-600 hover:bg-gray-50">{{ __('Gruppe verlassen') }}</button>
        <button type="button" @click="deleteGroup()" class="rounded border border-red-300 px-2 py-0.5 text-red-600 hover:bg-red-50">{{ __('Gruppe löschen') }}</button>
    </div>
</div>

{{--
    Ralf, 2026-09-13: "ich habe wie wild auf Speichern geklickt, weil ich
    dachte, ich muss die Gruppenzuordnung ja speichern" - Neu-Anlegen-Feld
    +Button erst hinter einem eigenen "Neue Gruppe"-Button verstecken
    (gleiches Prinzip wie admin/attributes "+ Neu"), statt Eingabefeld +
    "Speichern" dauerhaft sichtbar direkt unter den Häkchen-Toggle-Aktionen
    zu zeigen - sah sonst wie ein ausstehender Speichervorgang aus.
--}}
<div class="mt-3 border-t border-gray-200 pt-2" x-data="{ creatingGroup: false }">
    <button
        type="button"
        x-show="! creatingGroup"
        @click="creatingGroup = true; $nextTick(() => $refs.newGroupInput.focus())"
        class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
    >
        + {{ __('Neue Gruppe') }}
    </button>
    <div x-show="creatingGroup" x-cloak class="flex items-center gap-1.5">
        <input
            type="text"
            x-ref="newGroupInput"
            x-model="newGroupName"
            placeholder="{{ __('Name') }}"
            @keydown.enter="await createGroup(); creatingGroup = false"
            class="min-w-0 flex-1 rounded-md border-gray-300 text-xs"
        >
        <button type="button" @click="await createGroup(); creatingGroup = false" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Speichern') }}
        </button>
    </div>
</div>
