<x-admin-layout>
    @if (session('status') === 'teams-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if ($errors->any())
        <div class="mb-3 shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="flex flex-1 min-h-0 gap-4">
        {{-- Links: die Teams der Organisation. Die Eingabezeile für ein neues Team erscheint erst nach Klick auf "+ Neu". --}}
        <div x-data="{ newTeam: {{ $errors->has('name') && ! $selected ? 'true' : 'false' }} }" class="flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-white">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Teams') }}</span>
                <button type="button" @click="newTeam = ! newTeam; if (newTeam) $nextTick(() => $refs.newTeamName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Neues Team anlegen') }}">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => window.keepListScroll($el, 'list-scroll:teams'))">
                <form x-show="newTeam" x-cloak method="POST" action="{{ route('admin.teams.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    @csrf
                    <input type="text" name="name" x-ref="newTeamName" value="{{ $selected ? '' : old('name') }}" placeholder="{{ __('Bezeichnung') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    <input type="text" name="short_name" placeholder="{{ __('Kürzel') }}" maxlength="20" class="w-16 shrink-0 rounded-md border-gray-300 text-xs">
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </form>

                @forelse ($teams as $team)
                    <a
                        href="{{ route('admin.teams', ['team' => $team->id]) }}"
                        onclick="return window.navigateOrConfirm(event)"
                        @if ($selected?->id === $team->id) data-selected @endif
                        class="flex items-center gap-1.5 rounded px-2 py-1 {{ $selected?->id === $team->id ? 'bg-indigo-50 font-medium text-indigo-700' : ($team->active ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-400 hover:bg-gray-50') }}"
                    >
                        <span class="flex-1">{{ $team->name }}{{ ! $team->active ? ' [i]' : '' }}</span>
                        @if ($team->short_name)
                            <span class="text-xs text-gray-400">{{ $team->short_name }}</span>
                        @endif
                        <span class="text-xs text-gray-400" title="{{ trans_choice(':count Mitglied|:count Mitglieder', $team->members_count, ['count' => $team->members_count]) }}">({{ $team->members_count }})</span>
                    </a>
                @empty
                    <div class="px-2 py-3 text-xs text-gray-400">{{ __('Noch kein Team angelegt. Wenn Sie ein Team brauchen, dann legen Sie es oben mit „+ Neu“ an.') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Rechts: Stammdaten und Mitglieder des gewählten Teams. --}}
        <div class="flex min-h-0 flex-1 flex-col rounded-lg border border-gray-200 bg-white">
            @if ($selected)
                <form
                    method="POST"
                    action="{{ route('admin.teams.update', $selected) }}"
                    class="flex min-h-0 flex-1 flex-col"
                    x-data="{
                        dirty: false,
                        picking: false,
                        showInactive: false,
                        pick: '',
                        people: @js($people->map(fn ($person) => ['id' => $person->id, 'name' => $person->last_name.', '.$person->first_name, 'active' => (bool) $person->active, 'org' => $person->tenant?->name ?? '?'])->values()),
                        roles: @js($roles->mapWithKeys(fn ($role, $id) => [(string) $id => $role])),
                        labels: @js(['member' => __('Mitglied'), 'lead' => __('Teamleiter'), 'deputy' => __('stv. Teamleiter')]),
                        collator: new Intl.Collator('de'),
                        get members() {
                            return this.people.filter((p) => this.roles[p.id] !== undefined);
                        },
                        get memberGroups() {
                            const groups = {};
                            this.members.forEach((p) => { (groups[p.org] = groups[p.org] || []).push(p); });
                            return Object.keys(groups).sort((a, b) => this.collator.compare(a, b))
                                .map((org) => ({ org, people: groups[org].sort((a, b) => this.collator.compare(a.name, b.name)) }));
                        },
                        get candidateGroups() {
                            const groups = {};
                            this.people.filter((p) => this.roles[p.id] === undefined && (this.showInactive || p.active))
                                .forEach((p) => { (groups[p.org] = groups[p.org] || []).push(p); });
                            return Object.keys(groups).sort((a, b) => this.collator.compare(a, b))
                                .map((org) => ({ org, people: groups[org].sort((a, b) => this.collator.compare(a.name, b.name)) }));
                        },
                        add() {
                            if (! this.pick) return;
                            this.roles[this.pick] = 'member';
                            this.pick = '';
                            this.dirty = true;
                        },
                        async remove(person) {
                            if (! await window.confirmDialog({
                                title: {{ \Illuminate\Support\Js::from(__('Mitglied entfernen?')) }},
                                message: person.name + {{ \Illuminate\Support\Js::from(__(' wird aus diesem Team entfernt. Die Person selbst bleibt erhalten. Die Änderung gilt, sobald Sie das Team speichern.')) }},
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('Entfernen')) }},
                                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
                            })) return;
                            delete this.roles[person.id];
                            this.dirty = true;
                        },
                    }"
                    x-init="window.adminPageIsDirty = () => dirty;"
                    @input="dirty = true"
                    @change="dirty = true"
                    @submit="dirty = false"
                >
                    @csrf
                    <div class="shrink-0 space-y-2 border-b border-gray-100 p-3">
                        <div class="flex items-center gap-2">
                            <input type="text" name="name" value="{{ old('name', $selected->name) }}" required maxlength="255" placeholder="{{ __('Bezeichnung') }}" class="flex-1 rounded-md border-gray-300 py-1 text-sm font-medium text-gray-900">
                            <input type="text" name="short_name" value="{{ old('short_name', $selected->short_name) }}" maxlength="20" placeholder="{{ __('Kürzel') }}" class="w-20 rounded-md border-gray-300 py-1 text-sm text-gray-900">
                            <label class="flex shrink-0 items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" name="active" value="1" @checked(old('active', $selected->active)) class="rounded border-gray-300">
                                {{ __('Aktiv') }}
                            </label>
                        </div>
                        <textarea name="description" rows="2" maxlength="2000" placeholder="{{ __('Beschreibung (optional)') }}" class="w-full rounded-md border-gray-300 text-sm text-gray-700">{{ old('description', $selected->description) }}</textarea>
                        <p class="text-xs text-gray-400">{{ __('Wenn ein Team inaktiv ist, dann bleibt es mit seinen Mitgliedern erhalten, ist aber für neue Zuweisungen nicht mehr wählbar.') }}</p>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col p-3">
                        <div class="mb-2 flex shrink-0 items-center justify-between">
                            <div class="text-xs font-semibold text-gray-500">
                                {{ __('Mitglieder') }} <span class="font-normal text-gray-400" x-text="'(' + members.length + ')'"></span>
                            </div>
                            <button type="button" @click="picking = ! picking; if (picking) $nextTick(() => $refs.pickSelect.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Person aus Ihren berechtigten Organisationen zum Team hinzufügen') }}">
                                + {{ __('Person hinzufügen') }}
                            </button>
                        </div>

                        <div x-show="picking" x-cloak class="mb-2 flex shrink-0 flex-wrap items-center gap-2 rounded-md border border-gray-200 p-2">
                            <select x-ref="pickSelect" x-model="pick" @change.stop="add()" class="min-w-0 flex-1 rounded-md border-gray-300 py-1 text-sm">
                                <option value="">{{ __('– Person wählen –') }}</option>
                                <template x-for="group in candidateGroups" :key="group.org">
                                    <optgroup :label="group.org">
                                        <template x-for="p in group.people" :key="p.id">
                                            <option :value="p.id" x-text="p.name + (p.active ? '' : ' [i]')"></option>
                                        </template>
                                    </optgroup>
                                </template>
                            </select>
                            <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" x-model="showInactive" @input.stop @change.stop class="rounded border-gray-300">
                                {{ __('Inaktive Personen zeigen') }}
                            </label>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto text-sm">
                            <div x-show="members.length === 0" class="py-3 text-gray-400">{{ __('Noch keine Mitglieder. Wenn Sie Personen brauchen, dann fügen Sie sie oben mit „+ Person hinzufügen“ hinzu.') }}</div>

                            <table x-show="members.length > 0" class="w-full border-separate border-spacing-0">
                                <thead class="sticky top-0 bg-white text-xs text-gray-500">
                                    <tr>
                                        <th class="border-b border-gray-200 py-1 text-left font-medium">{{ __('Name') }}</th>
                                        <th class="w-24 border-b border-gray-200 py-1 text-center font-medium">{{ __('Mitglied') }}</th>
                                        <th class="w-24 border-b border-gray-200 py-1 text-center font-medium">{{ __('Teamleiter') }}</th>
                                        <th class="w-28 border-b border-gray-200 py-1 text-center font-medium">{{ __('stv. Teamleiter') }}</th>
                                        <th class="w-10 border-b border-gray-200 py-1"></th>
                                    </tr>
                                </thead>
                                <template x-for="group in memberGroups" :key="group.org">
                                    <tbody>
                                        <tr>
                                            <th colspan="5" class="border-y border-gray-300 bg-slate-100 px-2 py-1 text-left text-xs font-semibold text-slate-700" x-text="group.org"></th>
                                        </tr>
                                        <template x-for="p in group.people" :key="p.id">
                                            <tr class="hover:bg-gray-50">
                                                <td class="border-b border-gray-100 py-1 pl-2" :class="p.active ? 'text-gray-700' : 'text-gray-400'" x-text="p.name + (p.active ? '' : ' [i]')"></td>
                                                <template x-for="role in ['member', 'lead', 'deputy']" :key="role">
                                                    <td class="border-b border-gray-100 py-1 text-center">
                                                        <input type="radio" :name="'members[' + p.id + ']'" :value="role" :checked="roles[p.id] === role" @change="roles[p.id] = role" :title="labels[role]" class="border-gray-300">
                                                    </td>
                                                </template>
                                                <td class="border-b border-gray-100 py-1 text-center">
                                                    <button type="button" @click="remove(p)" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" title="{{ __('Aus dem Team entfernen') }}" aria-label="{{ __('Aus dem Team entfernen') }}">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </template>
                            </table>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center justify-between border-t border-gray-100 p-3">
                        <button
                            type="button"
                            @click="window.deleteWithConfirm(document.getElementById('team-delete-form'), { message: {{ \Illuminate\Support\Js::from(__('Dieses Team wirklich endgültig löschen? Die Personen selbst bleiben erhalten.')) }} })"
                            class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                        >{{ __('Löschen') }}</button>
                        <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.teams.destroy', $selected) }}" id="team-delete-form" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-gray-400">{{ __('Links ein Team auswählen oder mit „+ Neu“ ein neues anlegen.') }}</div>
            @endif
        </div>
    </div>
</x-admin-layout>
