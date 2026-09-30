<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight text-gray-800">{{ __('Kalender') }}</h2>
    </x-slot>

    <div
        class="flex h-full flex-col gap-2 p-2 sm:p-3"
        x-data="{
            editing: @js(old('entry_id') !== null),
            entryId: @js(old('entry_id')),
            targetPersonId: @js(old('person_id')),
            personName: '',
            createdMeta: '',
            type: @js(old('type', \App\Models\CalendarEntry::TYPE_ABSENCE)),
            startsOn: @js(old('starts_on', $monthStart->toDateString())),
            endsOn: @js(old('ends_on', $monthStart->toDateString())),
            note: @js(old('note', '')),
            createUrl: @js(route('kalender.eintraege.store')),
            updateUrlTemplate: @js(route('kalender.eintraege.update', ['calendarEntry' => '__ID__'])),
            deleteUrlTemplate: @js(route('kalender.eintraege.destroy', ['calendarEntry' => '__ID__'])),
            openCreate(date, personId, personName) {
                this.editing = false;
                this.entryId = null;
                this.targetPersonId = personId;
                this.personName = personName;
                this.createdMeta = '';
                this.type = @js(\App\Models\CalendarEntry::TYPE_ABSENCE);
                this.startsOn = date;
                this.endsOn = date;
                this.note = '';
                this.openModal();
            },
            openEdit(entry) {
                this.editing = true;
                this.entryId = entry.id;
                this.targetPersonId = entry.person_id;
                this.personName = entry.person_name;
                this.createdMeta = entry.created_meta;
                this.type = entry.type;
                this.startsOn = entry.starts_on;
                this.endsOn = entry.ends_on;
                this.note = entry.note || '';
                this.openModal();
            },
            openModal() {
                this.$nextTick(() => {
                    window.calendarEntrySnapshot = new FormData(document.getElementById('calendar-entry-form'));
                    this.$dispatch('open-modal', 'calendar-entry');
                    setTimeout(() => this.$refs.entryType?.focus(), 100);
                });
            },
            async deleteEntry() {
                if (! await window.confirmDialog({
                    title: @js(__('Kalendereintrag löschen')),
                    message: @js(__('Diesen Kalendereintrag wirklich endgültig löschen?')),
                    confirmLabel: @js(__('Löschen')),
                    cancelLabel: @js(__('Abbrechen')),
                })) return;
                this.$refs.deleteForm.submit();
            },
        }"
    >
        @if (session('status') === 'calendar-entry-saved')
            <x-flash-message class="shrink-0 px-3 py-2 text-sm">
                {{ __('Gespeichert.') }}
            </x-flash-message>
        @elseif (session('status') === 'calendar-entry-deleted')
            <x-flash-message class="shrink-0 px-3 py-2 text-sm">
                {{ __('Kalendereintrag gelöscht.') }}
            </x-flash-message>
        @endif

        <div class="flex shrink-0 flex-wrap items-center gap-4 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <form method="GET" action="{{ route('kalender') }}" class="flex items-center gap-2">
                <input type="hidden" name="month" value="{{ $month }}">
                <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                    <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach ($years as $itemYear)
                            <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                        @endforeach
                    </select>
                </label>
            </form>

            <div class="flex items-center gap-3">
                @if ($previousMonth)
                    <a href="{{ route('kalender', ['year' => $previousMonth->year, 'month' => $previousMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Voriger Monat') }}" aria-label="{{ __('Voriger Monat') }}">‹</a>
                @else
                    <span class="p-1 text-gray-300">‹</span>
                @endif
                <span class="min-w-36 text-center font-semibold text-gray-800">{{ $monthStart->translatedFormat('F Y') }}</span>
                @if ($nextMonth)
                    <a href="{{ route('kalender', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Nächster Monat') }}" aria-label="{{ __('Nächster Monat') }}">›</a>
                @else
                    <span class="p-1 text-gray-300">›</span>
                @endif
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-3 text-[11px] text-gray-500">
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>{{ __('Abwesenheit') }}</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ __('Mobile-Office') }}</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-violet-500"></span>{{ __('Auswärtstermin') }}</span>
            </div>
        </div>

        <p class="shrink-0 px-1 text-xs text-gray-400">
            {{ __('Gewünschten Tag anklicken, um einen neuen Eintrag anzulegen oder zu bearbeiten.') }}
        </p>

        <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-max border-collapse text-xs">
                <thead class="sticky top-0 z-10 bg-gray-50 text-gray-700">
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($weekSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 px-1 py-0.5 text-center font-semibold" title="{{ $segment['year'] }}">
                                {{ __('KW') }} {{ $segment['week'] }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50 px-2 py-1"></th>
                        @foreach ($days as $day)
                            @php
                                $isHoliday = $holidaysByDate->has($day->toDateString());
                            @endphp
                            <th class="min-w-10 border-b border-r border-gray-200 px-1 py-1 text-center font-medium {{ $isHoliday ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : '') }}" title="{{ $day->translatedFormat('l, d.m.Y') }}">
                                <span class="block text-[10px] text-gray-400">{{ $day->translatedFormat('D') }}</span>
                                <span class="block tabular-nums">{{ $day->format('d') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-200">
                        <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white px-2 py-1.5 text-left font-medium text-gray-700">{{ __('Feiertage') }}</th>
                        @foreach ($days as $day)
                            @php
                                $holidays = $holidaysByDate->get($day->toDateString(), collect());
                            @endphp
                            <td class="border-r border-gray-100 px-1 py-1.5 text-center {{ $holidays->isNotEmpty() ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : '') }}" @if ($holidays->isNotEmpty()) title="{{ $holidays->pluck('name')->implode(', ') }}" @endif>
                                @if ($holidays->isNotEmpty())
                                    <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                @endif
                            </td>
                        @endforeach
                    </tr>

                    @forelse ($personGroups as $tenantId => $people)
                        @if ($groupPeopleByTenant)
                            <tr>
                                <th colspan="{{ $days->count() + 1 }}" class="sticky left-0 border-y border-gray-300 bg-slate-100 px-2 py-1 text-left font-semibold text-slate-700">
                                    {{ $tenants->get($tenantId)?->name ?? __('Unbekannter Kunde') }}
                                </th>
                            </tr>
                        @endif
                        @foreach ($people as $person)
                            <tr class="border-b border-gray-100">
                                <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white px-2 py-1.5 text-left font-medium text-gray-700" title="{{ $person->tenant?->name }}">
                                    {{ $person->fullName() }}
                                </th>
                                @foreach ($days as $day)
                                    @php
                                        $date = $day->toDateString();
                                        $personEntries = $entriesByPersonAndDate->get($person->id.'|'.$date, collect());
                                        $isHoliday = $holidaysByDate->has($date);
                                        $tooltip = $personEntries->map(fn ($entry) => $entry->typeLabel().($entry->note ? ' ('.$entry->note.')' : ''))->implode(' · ');
                                        $canCreate = ($person->id === $ownPersonId || $canManageOthers) && $person->calendar_enabled;
                                        $firstEditableEntry = $personEntries->first(fn ($entry) => $entry->person_id === $ownPersonId || $canManageOthers);
                                        $firstEditData = $firstEditableEntry ? [
                                            'id' => $firstEditableEntry->id,
                                            'person_id' => $person->id,
                                            'person_name' => $person->fullName(),
                                            'created_meta' => __('Angelegt von :name am :date um :time Uhr', [
                                                'name' => $firstEditableEntry->createdByUser?->person?->fullName() ?? $firstEditableEntry->createdByUser?->name ?? __('Unbekannt'),
                                                'date' => $firstEditableEntry->created_at->local()->format('d.m.Y'),
                                                'time' => $firstEditableEntry->created_at->local()->format('H:i'),
                                            ]),
                                            'type' => $firstEditableEntry->type,
                                            'starts_on' => $firstEditableEntry->starts_on->toDateString(),
                                            'ends_on' => $firstEditableEntry->ends_on->toDateString(),
                                            'note' => $firstEditableEntry->note,
                                        ] : null;
                                    @endphp
                                    <td
                                        class="h-7 border-r border-gray-100 p-0 text-center {{ $isHoliday ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : '') }} {{ ($firstEditableEntry || $canCreate) ? 'cursor-pointer hover:bg-blue-50' : '' }}"
                                        @if ($tooltip) title="{{ $tooltip }}" @endif
                                        @if ($firstEditableEntry)
                                            x-on:click="openEdit(@js($firstEditData))"
                                        @elseif ($canCreate)
                                            x-on:click="openCreate(@js($date), @js($person->id), @js($person->fullName()))"
                                        @endif
                                    >
                                        <span class="inline-flex max-w-9 flex-wrap items-center justify-center gap-0.5">
                                            @foreach ($personEntries as $entry)
                                                @php
                                                    $mayEditEntry = $entry->person_id === $ownPersonId || $canManageOthers;
                                                    $entryTooltip = $entry->typeLabel().($entry->note ? ' ('.$entry->note.')' : '');
                                                    $editData = [
                                                        'id' => $entry->id,
                                                        'person_id' => $person->id,
                                                        'person_name' => $person->fullName(),
                                                        'created_meta' => __('Angelegt von :name am :date um :time Uhr', [
                                                            'name' => $entry->createdByUser?->person?->fullName() ?? $entry->createdByUser?->name ?? __('Unbekannt'),
                                                            'date' => $entry->created_at->local()->format('d.m.Y'),
                                                            'time' => $entry->created_at->local()->format('H:i'),
                                                        ]),
                                                        'type' => $entry->type,
                                                        'starts_on' => $entry->starts_on->toDateString(),
                                                        'ends_on' => $entry->ends_on->toDateString(),
                                                        'note' => $entry->note,
                                                    ];
                                                @endphp
                                                @if ($mayEditEntry)
                                                    <button type="button" x-on:click.stop="openEdit(@js($editData))" class="inline-flex h-4 w-3 items-center justify-center" title="{{ $entryTooltip }}">
                                                        <span class="h-2 w-2 rounded-full {{ $entry->dotClass() }}"></span>
                                                    </button>
                                                @else
                                                    <span class="h-2 w-2 rounded-full {{ $entry->dotClass() }}" title="{{ $entryTooltip }}"></span>
                                                @endif
                                            @endforeach
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="{{ $days->count() + 1 }}" class="p-6 text-center text-gray-400">{{ __('Keine für den Kalender freigeschalteten Personen gefunden.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-modal name="calendar-entry" max-width="md" :show="$errors->any()" :dirty-check="'calendarEntryIsDirty'" :draggable="true">
            <form id="calendar-entry-form" method="POST" :action="editing ? updateUrlTemplate.replace('__ID__', entryId) : createUrl" class="flex max-h-[85vh] flex-col">
                @csrf
                <input type="hidden" name="_method" value="PUT" :disabled="! editing">
                <input type="hidden" name="entry_id" :value="entryId" :disabled="! editing">
                <input type="hidden" name="person_id" :value="targetPersonId">
                <input type="hidden" name="return_year" value="{{ $year }}">
                <input type="hidden" name="return_month" value="{{ $month }}">

                <div class="flex shrink-0 cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3" data-drag-handle title="{{ __('Ziehen zum Verschieben') }}">
                    <div>
                        <h3 class="font-semibold text-gray-900" x-text="editing ? @js(__('Kalendereintrag bearbeiten')) : @js(__('Kalendereintrag anlegen'))"></h3>
                        <p x-show="personName" x-text="personName" class="text-sm font-medium text-gray-700"></p>
                        <p x-show="editing && createdMeta" x-text="createdMeta" class="text-[11px] text-gray-400"></p>
                    </div>
                    <button type="button" @click="$dispatch('close-modal', 'calendar-entry')" class="text-xl leading-none text-gray-400 hover:text-gray-700" aria-label="{{ __('Schließen') }}">×</button>
                </div>

                <div class="space-y-3 overflow-y-auto px-4 py-3 text-sm">
                    <div>
                        <label for="calendar-entry-type" class="mb-1 block text-xs text-gray-500">{{ __('Art') }}</label>
                        <select id="calendar-entry-type" name="type" x-model="type" x-ref="entryType" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="{{ \App\Models\CalendarEntry::TYPE_ABSENCE }}">{{ __('Abwesenheit') }}</option>
                            <option value="{{ \App\Models\CalendarEntry::TYPE_MOBILE_OFFICE }}">{{ __('Mobile-Office') }}</option>
                            <option value="{{ \App\Models\CalendarEntry::TYPE_EXTERNAL_APPOINTMENT }}">{{ __('Auswärtstermin') }}</option>
                        </select>
                        @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="calendar-entry-start" class="mb-1 block text-xs text-gray-500">{{ __('Von') }}</label>
                            <input id="calendar-entry-start" type="date" name="starts_on" x-model="startsOn" class="w-full rounded-md border-gray-300 text-sm">
                            @error('starts_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="calendar-entry-end" class="mb-1 block text-xs text-gray-500">{{ __('Bis') }}</label>
                            <input id="calendar-entry-end" type="date" name="ends_on" x-model="endsOn" class="w-full rounded-md border-gray-300 text-sm">
                            @error('ends_on') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="calendar-entry-note" class="mb-1 block text-xs text-gray-500">{{ __('Erläuterung (optional)') }}</label>
                        <input id="calendar-entry-note" type="text" name="note" maxlength="255" x-model="note" class="w-full rounded-md border-gray-300 text-sm">
                        @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 px-4 py-3">
                    <button x-show="editing" type="button" @click="deleteEntry()" class="mr-auto rounded-md border border-red-300 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">{{ __('Löschen') }}</button>
                    <button type="button" @click="$dispatch('close-modal', 'calendar-entry')" class="rounded-md border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200">{{ __('Abbrechen') }}</button>
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                </div>
            </form>
            <form x-ref="deleteForm" method="POST" :action="deleteUrlTemplate.replace('__ID__', entryId)" class="hidden">
                @csrf
                @method('DELETE')
                <input type="hidden" name="return_year" value="{{ $year }}">
                <input type="hidden" name="return_month" value="{{ $month }}">
            </form>
        </x-modal>
    </div>

    <script>
        window.calendarEntrySnapshot = null;
        window.calendarEntryIsDirty = function () {
            const form = document.getElementById('calendar-entry-form');
            if (! form || ! window.calendarEntrySnapshot) return false;
            const current = new URLSearchParams(new FormData(form)).toString();
            const original = new URLSearchParams(window.calendarEntrySnapshot).toString();
            return current !== original;
        };
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('calendar-entry-form');
            if (form) window.calendarEntrySnapshot = new FormData(form);
        });
    </script>
</x-app-layout>
