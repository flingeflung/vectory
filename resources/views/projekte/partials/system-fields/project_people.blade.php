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
        <x-edit-icon-button x-show="!editingPeople" @click="editingPeople = true" :title="__('Ändern')" />
        <button type="button" x-show="editingPeople" x-cloak @click="editingPeople = false" class="{{ $secondaryBtn }}">
            {{ __('Fertig') }}
        </button>
    </div>

    <div x-show="!editingPeople">
        @php
            $groupedPeople = $project->projectPeople->groupBy('function_group_id');
            $effectiveGroupHours = $project->functionGroupHours->isNotEmpty()
                ? $project->functionGroupHours->pluck('pivot.planned_hours', 'id')
                : ($project->projectTemplate?->functionGroups?->pluck('pivot.planned_hours', 'id') ?? collect());
            // Unterscheidung wichtig: "niemand zugeordnet" (normal, Klick auf
            // "Ändern" hilft) vs. "Mandant hat noch gar keine Personen" (die
            // Zuordnung kann dann gar nicht klappen - eigener Hinweis statt
            // scheinbar funktionslosem Ändern-Dialog, siehe Ralf-Bug-Report zu
            // einem frisch angelegten Testmandanten ohne Personen).
            $hasAssignablePeople = $allFunctionGroups->contains(fn ($group) => $group->members->isNotEmpty());
        @endphp
        @if (! $hasAssignablePeople)
            <div class="mt-0.5 text-amber-700">{{ __('Für diese Firma sind noch keine Funktionsgruppen mit Personen angelegt. Bitte zuerst unter Admin > Personen & Rechte > Funktionsgruppen entsprechende Gruppen anlegen und Personen zuordnen.') }}</div>
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
                                <span class="{{ $entry->person->active ? '' : 'text-gray-400' }}">{{ $entry->person->fullName() }}{{ ! $entry->person->active ? ' [i]' : '' }}</span><x-absence-icon :person="$entry->person" />@if ($entry->is_primary)<span class="text-amber-500" title="{{ __('Erstansprechpartner') }}">&#9733;</span>@endif
                                @if ($entry->planned_hours !== null)<span class="text-gray-400">({{ number_format((float) $entry->planned_hours, 2, ',', '.') }} h)</span>@endif
                                @if (! $loop->last); @endif
                            @endforeach
                            @php
                                $plannedForGroup = (float) $entries->sum('planned_hours');
                                $groupTarget = $effectiveGroupHours->get($group->id);
                            @endphp
                            @if ($entries->contains(fn ($entry) => $entry->planned_hours === null))
                                <div class="text-amber-700">{{ __('Planstunden noch nicht vollständig verteilt') }}</div>
                            @elseif ($groupTarget !== null && abs($plannedForGroup - (float) $groupTarget) >= 0.01)
                                <div class="text-amber-700">{{ __('Verteilt: :distributed von :planned Stunden', ['distributed' => number_format($plannedForGroup, 2, ',', '.'), 'planned' => number_format((float) $groupTarget, 2, ',', '.')]) }}</div>
                            @endif
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
            <div class="text-amber-700">{{ __('Für diese Firma sind noch keine Funktionsgruppen mit Personen angelegt. Bitte zuerst unter Admin > Personen & Rechte > Funktionsgruppen entsprechende Gruppen anlegen und Personen zuordnen.') }}</div>
        @endif
        @foreach ($allFunctionGroups as $group)
            @php
                $currentEntries = $project->projectPeople->where('function_group_id', $group->id);
                $groupTarget = $effectiveGroupHours->get($group->id);
                $missingHoursCount = $currentEntries->whereNull('planned_hours')->count();
                $suggestedMissingHours = $missingHoursCount > 0 && $groupTarget !== null
                    ? max(0, ((float) $groupTarget - (float) $currentEntries->whereNotNull('planned_hours')->sum('planned_hours')) / $missingHoursCount)
                    : null;
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
                    <div class="mb-0.5 flex items-center justify-between font-medium text-gray-600">
                        <span>{{ $group->name }}</span>
                        <span class="font-normal text-gray-400">{{ __('Planstunden') }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5">
                        @foreach ($visibleMembers as $person)
                            @php $currentEntry = $currentEntries->firstWhere('person_id', $person->id); @endphp
                            <label class="grid grid-cols-[auto_auto_minmax(0,1fr)_5.5rem] items-center gap-1 {{ $person->active ? 'text-gray-600' : 'text-gray-400' }}">
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
                                <span class="min-w-0 truncate">{{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-absence-icon :person="$person" /></span>
                                <input
                                    type="number"
                                    name="project_people_hours[{{ $group->id }}][{{ $person->id }}]"
                                    value="{{ $currentEntry?->planned_hours ?? ($currentEntry && $suggestedMissingHours !== null ? number_format($suggestedMissingHours, 2, '.', '') : '') }}"
                                    min="0"
                                    step="0.25"
                                    class="w-full rounded border-gray-300 px-1 py-0.5 text-right text-xs"
                                    title="{{ __('Geplante Stunden dieser Person') }}"
                                    placeholder="{{ __('Std.') }}"
                                >
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
