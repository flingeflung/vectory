{{-- Ralf, 2026-09-12: eine über den WFS-Personen-Picker hinzugefügte
     Person landet sofort in project_people, dieses Feld sitzt aber in
     einem anderen Tab (Stammdaten) und gleicht sich sonst nicht von
     selbst ab - hört deshalb auf dasselbe window-Event-Muster wie die
     Bemerkungen/Änderungsprotokoll-Boxen (siehe project-notes.blade.php).
     Lädt sich NICHT neu, während gerade editiert wird (sonst gingen
     unfertige Änderungen verloren). --}}
@php
    $teamData = \App\Models\Team::assignableForTenant($project->tenant_id);
    // Welche Funktionsgruppen dieses Projekts bieten eine Person an? Nur dort lässt sich ein Teammitglied automatisch zuweisen.
    $groupsByPerson = [];
    foreach ($allFunctionGroups as $group) {
        if (! $group->active) {
            continue;
        }
        foreach ($group->members as $member) {
            $groupsByPerson[$member->id][] = ['id' => $group->id, 'short' => $group->short_name];
        }
    }
@endphp
<div
    id="project-people-field-{{ $project->id }}"
    x-data="{
        peopleModalOpen: false,
        peopleSnapshot: [],
        peopleTick: 0,
        selectedWorkflowId: {{ \Illuminate\Support\Js::from((string) ($project->workflow_id ?? '')) }},
        workflowGroups: {{ \Illuminate\Support\Js::from($availableWorkflows->mapWithKeys(fn ($workflow) => [(string) $workflow->id => $workflow->steps->flatMap->functionGroups->pluck('id')->unique()->values()->all()])) }},
        isWorkflowRelevant(groupId) {
            return (this.workflowGroups[this.selectedWorkflowId] || []).includes(groupId);
        },
        teams: {{ \Illuminate\Support\Js::from($teamData) }},
        groupsByPerson: {{ \Illuminate\Support\Js::from($groupsByPerson) }},
        teamId: '',
        teamResult: null,
        // Team zuweisen (Ralf, 2026-10-06): die Teamstruktur wird nicht gespeichert, es werden nur die einzelnen Personen
        // vorgemerkt (angehakt). Wer im Projekt schon zugewiesen ist (egal in welcher Funktionsgruppe), wird übersprungen; bestehende
        // Zuweisungen bleiben unverändert. Gespeichert wird wie sonst auch erst mit dem Speichern des Projekts.
        applyTeam() {
            const team = this.teams.find((t) => String(t.id) === String(this.teamId));
            if (! team) return;
            const result = { added: [], already: [], inactive: [], none: [] };
            team.members.forEach((member) => {
                const boxes = [...this.$root.querySelectorAll('input[data-person]')].filter((box) => box.dataset.person === String(member.id));
                // defaultChecked = so ist die Person gespeichert; checked ohne defaultChecked = nur vorgemerkt (z. B. durch ein Team vorher)
                if (boxes.some((box) => box.defaultChecked)) { result.already.push(member.name); return; }
                if (! member.active) { result.inactive.push(member.name); return; }
                // Die Person kommt in alle Funktionsgruppen dieses Projekts, in denen sie Mitglied ist.
                const groupIds = (this.groupsByPerson[member.id] || []).map((g) => String(g.id));
                const targets = boxes.filter((box) => groupIds.includes(String(box.dataset.group)));
                if (targets.length === 0) { result.none.push(member.name); return; }
                targets.forEach((box) => {
                    box.checked = true;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                });
                const shorts = (this.groupsByPerson[member.id] || []).filter((g) => targets.some((box) => String(box.dataset.group) === String(g.id))).map((g) => g.short);
                result.added.push(member.name + (targets.length > 1 ? ' (' + shorts.join(', ') + ')' : ''));
            });
            this.teamResult = result;
        },
        teamResultLines() {
            const r = this.teamResult;
            if (! r) return [];
            const lines = [];
            if (r.added.length) lines.push(r.added.length + ' ' + {{ \Illuminate\Support\Js::from(__('Person(en) vorgemerkt:')) }} + ' ' + r.added.join('; '));
            if (r.already.length) lines.push(r.already.length + ' ' + {{ \Illuminate\Support\Js::from(__('bereits zugewiesen, übersprungen:')) }} + ' ' + r.already.join('; '));
            if (r.inactive.length) lines.push(r.inactive.length + ' ' + {{ \Illuminate\Support\Js::from(__('inaktiv, übersprungen:')) }} + ' ' + r.inactive.join('; '));
            if (r.none.length) lines.push(r.none.length + ' ' + {{ \Illuminate\Support\Js::from(__('in keiner zuweisbaren Funktionsgruppe, übersprungen:')) }} + ' ' + r.none.join('; '));
            return lines;
        },
        // Overlay Projektbeteiligte Personen (Ralf, 2026-10-06): beim Öffnen wird der Stand der Häkchen gemerkt; wird das
        // Overlay ohne Speichern geschlossen, stellt es diesen Stand wieder her (die Felder liegen im Projektformular).
        peopleInputs() {
            const body = document.querySelector('#project-people-field-{{ $project->id }} [data-people-modal-body]');
            return body ? [...body.querySelectorAll('input[type=checkbox], input[type=radio]')] : [];
        },
        peopleDirty() {
            return this.peopleSnapshot.some((entry) => entry.el.checked !== entry.checked);
        },
        get hasPeopleChanges() {
            this.peopleTick;
            return this.peopleDirty();
        },
        onPeopleModal(open) {
            if (open && ! this.peopleModalOpen) {
                this.peopleModalOpen = true;
                this.peopleSnapshot = this.peopleInputs().map((el) => ({ el, checked: el.checked }));
                this.teamId = '';
                this.teamResult = null;
                this.peopleTick++;
            } else if (! open && this.peopleModalOpen) {
                this.peopleModalOpen = false;
                this.peopleSnapshot.forEach((entry) => {
                    if (entry.el.checked === entry.checked) return;
                    entry.el.checked = entry.checked;
                    entry.el.dispatchEvent(new Event('change', { bubbles: true }));
                });
                this.peopleSnapshot = [];
            }
        },
        init() {
            window.projectPeopleModalIsDirty = () => this.peopleDirty();
            this.onChanged = (e) => {
                if (e.detail.projectId === {{ $project->id }} && ! this.peopleModalOpen) {
                    this.refresh();
                }
            };
            window.addEventListener('project-people-changed', this.onChanged);
            this.onWorkflowSelectionChanged = (e) => this.selectedWorkflowId = String(e.detail.workflowId || '');
            window.addEventListener('project-workflow-selection-changed', this.onWorkflowSelectionChanged);
        },
        destroy() {
            delete window.projectPeopleModalIsDirty;
            window.removeEventListener('project-people-changed', this.onChanged);
            window.removeEventListener('project-workflow-selection-changed', this.onWorkflowSelectionChanged);
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
        <x-edit-icon-button :modal="'project-people-'.$project->id" :title="__('Ändern')" />
    </div>

    <div>
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
            <div class="mt-0.5 text-amber-700">{{ __('Für diese Organisation sind noch keine Funktionsgruppen mit Personen angelegt. Bitte zuerst unter Admin > Personen & Rechte > Funktionsgruppen entsprechende Gruppen anlegen und Personen zuordnen.') }}</div>
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
                                @if (! $loop->last); @endif
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

    <x-modal name="project-people-{{ $project->id }}" max-width="4xl" :draggable="true" :resizable="true" :dirty-check="'projectPeopleModalIsDirty'">
        <div x-effect="onPeopleModal(show)" class="flex h-full min-h-0 flex-col">
            <div data-drag-handle class="flex shrink-0 cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('Projektbeteiligte Personen') }}</h3>
                    <p class="text-xs text-gray-500">{{ $project->source_pn }} – {{ $project->title }}</p>
                </div>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-people-{{ $project->id }}' }))" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('Schließen') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="shrink-0 border-b border-gray-200 bg-white px-4 py-2 text-xs">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-500">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-blue-400"></span>{{ __('Im Workflow relevant') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400"></span>{{ __('Person fehlt') }}</span>
                </div>
                @if (count($teamData) > 0)
                    <div class="mt-2">
                        <select x-model="teamId" @change="teamResult = null; applyTeam()" class="w-full max-w-sm rounded-md border-gray-300 py-1 text-xs" title="{{ __('Alle Personen eines Teams auf einmal vormerken. Wer schon zugewiesen ist, wird übersprungen.') }}">
                            <option value="">{{ __('– Team wählen –') }}</option>
                            <template x-for="team in teams" :key="team.id">
                                <option :value="team.id" x-text="team.name + ' (' + team.members.length + ')'"></option>
                            </template>
                        </select>
                        <div x-show="teamResult" x-cloak class="mt-1 space-y-0.5 text-[11px] text-gray-600">
                            <template x-for="line in teamResultLines()" :key="line"><div x-text="line"></div></template>
                        </div>
                    </div>
                @endif
            </div>

            <div data-people-modal-body @change="peopleTick++" class="min-h-0 flex-1 overflow-y-auto p-4 text-xs">
        @if (! $hasAssignablePeople)
            <div class="text-amber-700">{{ __('Für diese Organisation sind noch keine Funktionsgruppen mit Personen angelegt. Bitte zuerst unter Admin > Personen & Rechte > Funktionsgruppen entsprechende Gruppen anlegen und Personen zuordnen.') }}</div>
        @endif
        <div class="grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-3">
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
            @if ($group->active || $currentEntries->isNotEmpty())
                <div
                    x-show="{{ $visibleMembers->isNotEmpty() ? 'true' : 'isWorkflowRelevant('.$group->id.')' }}"
                    x-cloak
                    x-data="{ assignedCount: {{ count($currentPersonIds) }} }"
                    :class="isWorkflowRelevant({{ $group->id }}) ? (assignedCount > 0 ? 'border-blue-400 bg-blue-50' : 'border-amber-400 bg-amber-50') : 'border-transparent bg-white'"
                    class="rounded border-l-2 px-2 py-1"
                >
                    <div class="mb-0.5 flex items-center justify-between gap-2 font-medium text-gray-600">
                        <span>{{ $group->name }}</span>
                        <span x-show="isWorkflowRelevant({{ $group->id }})" x-cloak :class="assignedCount > 0 ? 'text-blue-600' : 'text-amber-700'" class="shrink-0 text-[10px]" x-text="assignedCount > 0 ? {{ \Illuminate\Support\Js::from(__('Im Workflow')) }} : {{ \Illuminate\Support\Js::from(__('Person fehlt')) }}"></span>
                    </div>
                    @if ($visibleMembers->isEmpty())
                        <div class="text-[11px] text-amber-700">{{ __('Keine zuweisbare Person verfügbar.') }}</div>
                    @else
                        <div class="space-y-0.5">
                            @foreach ($visibleMembers as $person)
                            <label class="grid grid-cols-[auto_auto_minmax(0,1fr)] items-center gap-1 {{ $person->active ? 'text-gray-600' : 'text-gray-400' }}">
                                <input
                                    type="checkbox"
                                    name="project_people[{{ $group->id }}][]"
                                    value="{{ $person->id }}"
                                    data-person="{{ $person->id }}"
                                    data-group="{{ $group->id }}"
                                    class="shrink-0 rounded border-gray-300"
                                    @change="assignedCount += $event.target.checked ? 1 : -1"
                                    @checked(in_array($person->id, $currentPersonIds, true))
                                >
                                <input
                                    type="radio"
                                    name="project_people_primary[{{ $group->id }}]"
                                    value="{{ $person->id }}"
                                    class="shrink-0"
                                    title="{{ __('Als Erstansprechpartner markieren') }}"
                                    onclick="if (! this.previousElementSibling.checked) { this.previousElementSibling.checked = true; this.previousElementSibling.dispatchEvent(new Event('change', { bubbles: true })); }"
                                    @checked($currentPrimaryId === $person->id)
                                >
                                <span class="min-w-0 truncate">{{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-absence-icon :person="$person" /></span>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
        </div>
            </div>

            <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 bg-white px-4 py-3">
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-people-{{ $project->id }}' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover whitespace-nowrap">{{ __('Abbrechen') }}</button>
                <button type="submit" form="project-detail-form" x-show="hasPeopleChanges" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover whitespace-nowrap">{{ __('Speichern') }}</button>
            </div>
        </div>
    </x-modal>
</div>
