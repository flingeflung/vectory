<div x-data="{ editingPeople: false }">
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
    </div>

    <div x-show="editingPeople" x-cloak class="mt-0.5 max-h-56 overflow-y-auto rounded border border-gray-300 bg-white p-2 text-xs space-y-2">
        @if (! $hasAssignablePeople)
            <div class="text-amber-700">{{ __('Für diesen Mandanten sind noch keine Personen angelegt. Wenn hier jemand zugeordnet werden soll, dann zuerst unter :location Personen anlegen.', ['location' => \App\Models\SystemSetting::tenantConfigLocation()]) }}</div>
        @endif
        @foreach ($allFunctionGroups as $group)
            @php
                $currentEntries = $project->projectPeople->where('function_group_id', $group->id);
            @endphp
            {{-- Inaktive Funktionsgruppen werden für NEUE Zuordnungen
                 nicht mehr angeboten, bleiben aber sichtbar/editierbar,
                 wenn hier im Projekt schon jemand darüber zugeordnet
                 ist - sonst würde ein Speichern diese Zuordnung
                 stillschweigend entfernen (ihre Checkboxen kämen ja
                 gar nicht mehr im Formular vor). --}}
            @if ($group->members->isNotEmpty() && ($group->active || $currentEntries->isNotEmpty()))
                @php
                    $currentPersonIds = $currentEntries->pluck('person_id')->all();
                    $currentPrimaryId = optional($currentEntries->firstWhere('is_primary', true))->person_id;
                @endphp
                <div>
                    <div class="mb-0.5 font-medium text-gray-600">{{ $group->name }}</div>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-0.5">
                        @foreach ($group->members as $person)
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
