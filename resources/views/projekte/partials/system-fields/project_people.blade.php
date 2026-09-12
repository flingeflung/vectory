{{-- Ralf, 2026-09-12: eine über den WFS-Personen-Picker hinzugefügte
     Person landet sofort in project_people, dieses Feld sitzt aber in
     einem anderen Tab (Stammdaten) und gleicht sich sonst nicht von
     selbst ab - hört deshalb auf dasselbe window-Event-Muster wie die
     Bemerkungen/Änderungsprotokoll-Boxen (siehe project-notes.blade.php).
     Lädt sich NICHT neu, während gerade editiert wird (sonst gingen
     unfertige Änderungen verloren). --}}
<div
    id="project-people-field-{{ $project->id }}"
    x-data="{
        editingPeople: false,
        init() {
            this.onChanged = (e) => {
                if (e.detail.projectId === {{ $project->id }} && ! this.editingPeople) {
                    this.refresh();
                }
            };
            window.addEventListener('project-people-changed', this.onChanged);
        },
        destroy() {
            window.removeEventListener('project-people-changed', this.onChanged);
        },
        async refresh() {
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.projektbeteiligte.show', $project)) }});
            if (! response.ok) return;
            document.getElementById({{ \Illuminate\Support\Js::from('project-people-field-'.$project->id) }}).outerHTML = await response.text();
            // Verhindert eine fälschliche Ungespeichert-Meldung beim
            // Schließen (siehe Kommentar bei window.resnapshotProjectOverlay).
            window.resnapshotProjectOverlay?.();
        },
    }"
>
    <div class="flex items-center gap-2">
        <label class="text-xs text-gray-500">{{ __('Projektbeteiligte Personen') }}</label>
        <button type="button" @click="editingPeople = !editingPeople" class="{{ $secondaryBtn }}">
            <span x-show="!editingPeople">{{ __('Ändern') }}</span>
            <span x-show="editingPeople" x-cloak>{{ __('Fertig') }}</span>
        </button>
    </div>

    <div x-show="!editingPeople">
        @php
            $groupedPeople = $project->projectPeople->groupBy('function_group_id');
            // Unterscheidung wichtig: "niemand zugeordnet" (normal, Klick auf
            // "Ändern" hilft) vs. "Mandant hat noch gar keine Personen" (die
            // Zuordnung kann dann gar nicht klappen - eigener Hinweis statt
            // scheinbar funktionslosem Ändern-Dialog, siehe Ralf-Bug-Report zu
            // einem frisch angelegten Testmandanten ohne Personen).
            $hasAssignablePeople = $allFunctionGroups->contains(fn ($group) => $group->members->isNotEmpty());
        @endphp
        @if (! $hasAssignablePeople)
            <div class="mt-0.5 text-amber-700">{{ __('Für diesen Mandanten sind noch keine Personen angelegt. Wenn hier jemand zugeordnet werden soll, dann zuerst unter :location Personen anlegen.', ['location' => \App\Models\SystemSetting::tenantConfigLocation()]) }}</div>
        @elseif ($groupedPeople->isEmpty())
            <div class="mt-0.5 text-gray-400">&ndash; {{ __('Keine Personen zugeordnet') }} &ndash;</div>
        @else
            <div class="mt-0.5 grid grid-cols-2 gap-x-4 gap-y-0.5 text-gray-700">
                @foreach ($allFunctionGroups as $group)
                    @php $entries = $groupedPeople->get($group->id); @endphp
                    @if ($entries)
                        <div>
                            <span class="text-gray-500">{{ $group->short_name }}:</span>
                            @foreach ($entries as $entry)
                                {{-- Ralf: "Nachname, Vorname" pro Person UND ", " zwischen mehreren
                                     Personen war zweideutig lesbar - Trennzeichen zwischen Personen
                                     deshalb Semikolon statt Komma. --}}
                                <span class="{{ $entry->person->active ? '' : 'text-gray-400' }}">{{ $entry->person->fullName() }}{{ ! $entry->person->active ? ' [i]' : '' }}</span>@if ($entry->is_primary)<span class="text-amber-500" title="{{ __('Erstansprechpartner') }}">&#9733;</span>@endif @if (! $loop->last); @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Ralf, 2026-09-12: eine inaktive zugeordnete Person soll auffallen,
             nicht nur an der grauen Schrift/dem "[i]" im Namen erkennbar
             sein - direkter roter Hinweis statt rein passiver Markierung. --}}
        @php $inactivePeople = $project->projectPeople->filter(fn ($entry) => ! $entry->person->active)->pluck('person')->unique('id'); @endphp
        @if ($inactivePeople->isNotEmpty())
            <div class="mt-1 text-red-600">
                @if ($inactivePeople->count() === 1)
                    {{ __(':name ist inaktiv, bitte prüfen!', ['name' => $inactivePeople->first()->fullName()]) }}
                @else
                    {{ __(':names sind inaktiv, bitte prüfen!', ['names' => $inactivePeople->map->fullName()->join('; ')]) }}
                @endif
            </div>
        @endif
    </div>

    <div x-show="editingPeople" x-cloak class="mt-0.5 max-h-56 overflow-y-auto rounded border border-gray-300 bg-white p-2 text-xs space-y-2">
        @if (! $hasAssignablePeople)
            <div class="text-amber-700">{{ __('Für diesen Mandanten sind noch keine Personen angelegt. Wenn hier jemand zugeordnet werden soll, dann zuerst unter :location Personen anlegen.', ['location' => \App\Models\SystemSetting::tenantConfigLocation()]) }}</div>
        @endif
        @foreach ($allFunctionGroups as $group)
            @php
                $currentEntries = $project->projectPeople->where('function_group_id', $group->id);
            @endphp
            @php
                $currentPersonIds = $currentEntries->pluck('person_id')->all();
                $currentPrimaryId = optional($currentEntries->firstWhere('is_primary', true))->person_id;
                // Ralf, 2026-09-12: "Wenn Pauline inaktiv ist, möchte ich sie
                // auch nicht mehr zur Zuweisung sehen" - eine inaktive
                // Person taucht hier nur noch auf, wenn sie schon zugeordnet
                // ist (dann bleibt sie sichtbar/entfernbar, sonst würde ein
                // Speichern sie stillschweigend rauswerfen).
                $visibleMembers = $group->members->filter(fn ($person) => $person->active || in_array($person->id, $currentPersonIds, true));
            @endphp
            {{-- Inaktive Funktionsgruppen werden für NEUE Zuordnungen
                 nicht mehr angeboten, bleiben aber sichtbar/editierbar,
                 wenn hier im Projekt schon jemand darüber zugeordnet
                 ist - sonst würde ein Speichern diese Zuordnung
                 stillschweigend entfernen (ihre Checkboxen kämen ja
                 gar nicht mehr im Formular vor). --}}
            @if ($visibleMembers->isNotEmpty() && ($group->active || $currentEntries->isNotEmpty()))
                <div>
                    <div class="mb-0.5 font-medium text-gray-600">{{ $group->name }}</div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5">
                        @foreach ($visibleMembers as $person)
                            <label class="flex items-center gap-1 {{ $person->active ? 'text-gray-600' : 'text-gray-400' }}">
                                <input
                                    type="checkbox"
                                    name="project_people[{{ $group->id }}][]"
                                    value="{{ $person->id }}"
                                    class="shrink-0 rounded border-gray-300"
                                    @checked(in_array($person->id, $currentPersonIds, true))
                                >
                                <input
                                    type="radio"
                                    name="project_people_primary[{{ $group->id }}]"
                                    value="{{ $person->id }}"
                                    class="shrink-0"
                                    title="{{ __('Als Erstansprechpartner markieren') }}"
                                    onclick="this.previousElementSibling.checked = true"
                                    @checked($currentPrimaryId === $person->id)
                                >
                                {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
