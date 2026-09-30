<x-planning-layout>
    @php
        $personQuery = ['person_filter' => 1, 'people' => $selectedPersonIds->all()];
        $planningUrl = fn (array $overrides = []) => route('planung.projektplanung', array_merge([
            'view' => $displayMode,
            'year' => $year,
            'month' => $month,
        ], $personQuery, $overrides));
        $showTenantGroups = $personGroups->count() > 1;
    @endphp

    <div class="mb-3 flex shrink-0 flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
            <a href="{{ $planningUrl(['view' => 'month']) }}" class="px-3 py-1.5 font-medium {{ $displayMode === 'month' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' }}">{{ __('Monatsansicht') }}</a>
            <a href="{{ $planningUrl(['view' => 'year']) }}" class="border-l border-gray-300 px-3 py-1.5 font-medium {{ $displayMode === 'year' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' }}">{{ __('Jahresansicht') }}</a>
        </div>

        <form method="GET" action="{{ route('planung.projektplanung') }}" class="flex items-center gap-2">
            <input type="hidden" name="view" value="{{ $displayMode }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="person_filter" value="1">
            @foreach ($selectedPersonIds as $personId)
                <input type="hidden" name="people[]" value="{{ $personId }}">
            @endforeach
            <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                    @foreach ($years as $itemYear)
                        <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                    @endforeach
                </select>
            </label>
        </form>

        @if ($displayMode === 'month')
            <div class="flex items-center gap-3">
                @if ($previousMonth)
                    <a href="{{ $planningUrl(['year' => $previousMonth->year, 'month' => $previousMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Voriger Monat') }}" aria-label="{{ __('Voriger Monat') }}">‹</a>
                @else
                    <span class="p-1 text-gray-300">‹</span>
                @endif
                <span class="min-w-36 text-center font-semibold text-gray-800">{{ $monthStart->translatedFormat('F Y') }}</span>
                @if ($nextMonth)
                    <a href="{{ $planningUrl(['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Nächster Monat') }}" aria-label="{{ __('Nächster Monat') }}">›</a>
                @else
                    <span class="p-1 text-gray-300">›</span>
                @endif
                <a href="{{ $planningUrl(['year' => now()->year, 'month' => now()->month]) }}" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('heute') }}</a>
            </div>
        @endif

        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'projektplanung-personen' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Personen') }} ({{ $selectedPersonIds->count() }})</button>
    </div>

    <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-max border-collapse text-xs">
            <thead class="sticky top-0 z-10 bg-gray-50 text-gray-700">
                @if ($displayMode === 'month')
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($dayWeekSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 px-1 py-0.5 text-center font-semibold">{{ __('KW') }} {{ $segment['week'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ __('Person') }}</th>
                        @foreach ($days as $day)
                            <th class="min-w-10 border-b border-r border-gray-200 px-1 py-1 text-center font-medium {{ $day->isToday() ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : '') }}" title="{{ $day->translatedFormat('l, d.m.Y') }}">
                                <span class="block text-[10px] text-gray-400">{{ $day->translatedFormat('D') }}</span>
                                <span class="block tabular-nums">{{ $day->format('d') }}</span>
                            </th>
                        @endforeach
                    </tr>
                @else
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($weekMonthSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 px-1 py-0.5 text-center font-semibold">{{ $segment['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ __('Person') }}</th>
                        @foreach ($weeks as $week)
                            <th class="min-w-12 border-b border-r border-gray-200 px-1 py-1 text-center font-medium" title="{{ $week['start']->format('d.m.Y') }}">{{ __('KW') }} {{ $week['number'] }}</th>
                        @endforeach
                    </tr>
                @endif
            </thead>
            <tbody>
                @forelse ($selectedPersonGroups as $tenantId => $people)
                    @if ($showTenantGroups)
                        <tr><th colspan="{{ ($displayMode === 'month' ? $days->count() : $weeks->count()) + 1 }}" class="sticky left-0 border-y border-gray-300 bg-slate-100 px-2 py-1 text-left font-semibold text-slate-700">{{ $tenants->get($tenantId)?->name ?? __('Unbekannter Kunde') }}</th></tr>
                    @endif
                    @foreach ($people as $person)
                        <tr class="border-b border-gray-100">
                            <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white px-2 py-1.5 text-left font-medium text-gray-700">{{ $person->last_name }}, {{ $person->first_name }}</th>
                            @if ($displayMode === 'month')
                                @foreach ($days as $day)
                                    <td class="h-7 border-r border-gray-100 {{ $day->isToday() ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : '') }}"></td>
                                @endforeach
                            @else
                                @foreach ($weeks as $week)
                                    <td class="h-7 border-r border-gray-100"></td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="{{ ($displayMode === 'month' ? $days->count() : $weeks->count()) + 1 }}" class="p-6 text-center text-gray-400">{{ __('Keine Personen ausgewählt.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="projektplanung-personen" max-width="md" :draggable="true">
        <form method="GET" action="{{ route('planung.projektplanung') }}" x-data x-on:open-modal.window="if ($event.detail === 'projektplanung-personen') $nextTick(() => $refs.firstPerson?.focus())">
            <input type="hidden" name="view" value="{{ $displayMode }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="person_filter" value="1">
            <div data-drag-handle class="flex cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Personen auswählen') }}</h3>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektplanung-personen' }))" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('Schließen') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
                <button type="button" @click="$refs.personList.querySelectorAll('input[type=checkbox]').forEach((input) => input.checked = true)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Alle') }}</button>
                <button type="button" @click="$refs.personList.querySelectorAll('input[type=checkbox]').forEach((input) => input.checked = false)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Keiner') }}</button>
            </div>
            <div x-ref="personList" class="max-h-[60vh] overflow-y-auto p-4 text-sm">
                @forelse ($personGroups as $tenantId => $people)
                    <div class="mb-4 last:mb-0">
                        <div class="mb-1 text-xs font-semibold text-gray-500">{{ $tenants->get($tenantId)?->name ?? __('Unbekannter Kunde') }}</div>
                        <div class="space-y-1">
                            @foreach ($people as $person)
                                <label class="flex items-center gap-2 rounded px-1 py-1 hover:bg-gray-50">
                                    <input @if ($loop->parent->first && $loop->first) x-ref="firstPerson" @endif type="checkbox" name="people[]" value="{{ $person->id }}" @checked($selectedPersonIds->contains($person->id)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span>{{ $person->last_name }}, {{ $person->first_name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400">{{ __('Keine Personen verfügbar.') }}</p>
                @endforelse
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 p-3">
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektplanung-personen' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Anwenden') }}</button>
            </div>
        </form>
    </x-modal>
</x-planning-layout>
