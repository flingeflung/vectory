<x-planning-layout>
    @php
        $personQuery = ['person_filter' => 1, 'people' => $selectedPersonIds->all()];
        $organizationQuery = ['organization_filter' => 1, 'organizations' => $selectedOrganizationIds->all()];
        $planningUrl = fn (array $overrides = []) => route('planung.projektplanung', array_merge([
            'view' => $displayMode,
            'content' => $contentMode,
            'year' => $year,
            'month' => $month,
        ], $personQuery, $organizationQuery, $overrides));
        $showTenantGroups = $personGroups->count() > 1;
    @endphp

    <div x-data="{ switchingView: null }" class="mb-3 flex shrink-0 flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
        <div :class="{ 'pointer-events-none opacity-60': switchingView !== null }" :aria-busy="switchingView !== null" class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
            <a href="{{ $planningUrl(['view' => 'month']) }}" @click="if (switchingView !== null) { $event.preventDefault() } else { switchingView = 'month' }" class="inline-flex items-center gap-1.5 px-3 py-1.5 font-medium {{ $displayMode === 'month' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' }}">
                <span x-show="switchingView === 'month'" x-cloak><x-loading-spinner class="h-3.5 w-3.5" /></span>
                {{ __('Monatsansicht') }}
            </a>
            <a href="{{ $planningUrl(['view' => 'year']) }}" @click="if (switchingView !== null) { $event.preventDefault() } else { switchingView = 'year' }" class="inline-flex items-center gap-1.5 border-l border-gray-300 px-3 py-1.5 font-medium {{ $displayMode === 'year' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' }}">
                <span x-show="switchingView === 'year'" x-cloak><x-loading-spinner class="h-3.5 w-3.5" /></span>
                {{ __('Jahresansicht') }}
            </a>
        </div>

        <div :class="{ 'pointer-events-none opacity-60': switchingView !== null }" :aria-busy="switchingView !== null" class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
            <a href="{{ $planningUrl(['content' => 'projects']) }}" @click="if (switchingView !== null) { $event.preventDefault() } else { switchingView = 'projects' }" class="inline-flex items-center gap-1.5 px-3 py-1.5 font-medium {{ $contentMode === 'projects' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' }}">
                <span x-show="switchingView === 'projects'" x-cloak><x-loading-spinner class="h-3.5 w-3.5" /></span>
                {{ __('Projekte') }}
            </a>
            <a href="{{ $planningUrl(['content' => 'utilization']) }}" @click="if (switchingView !== null) { $event.preventDefault() } else { switchingView = 'utilization' }" class="inline-flex items-center gap-1.5 border-l border-gray-300 px-3 py-1.5 font-medium {{ $contentMode === 'utilization' ? 'bg-btn-primary text-white' : 'bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' }}">
                <span x-show="switchingView === 'utilization'" x-cloak><x-loading-spinner class="h-3.5 w-3.5" /></span>
                {{ __('Auslastung') }}
            </a>
        </div>

        <form method="GET" action="{{ route('planung.projektplanung') }}" class="flex items-center gap-2">
            <input type="hidden" name="view" value="{{ $displayMode }}">
            <input type="hidden" name="content" value="{{ $contentMode }}">
            @if ($displayMode !== 'month')
                <input type="hidden" name="month" value="{{ $month }}">
            @endif
            <input type="hidden" name="person_filter" value="1">
            @foreach ($selectedPersonIds as $personId)
                <input type="hidden" name="people[]" value="{{ $personId }}">
            @endforeach
            <input type="hidden" name="organization_filter" value="1">
            @foreach ($selectedOrganizationIds as $organizationId)
                <input type="hidden" name="organizations[]" value="{{ $organizationId }}">
            @endforeach
            <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
                <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                    @foreach ($years as $itemYear)
                        <option value="{{ $itemYear }}" @selected($year === $itemYear)>{{ $itemYear }}</option>
                    @endforeach
                </select>
            </label>
            @if ($displayMode === 'month')
                <div class="flex items-center gap-1">
                @if ($previousMonth)
                    <a href="{{ $planningUrl(['year' => $previousMonth->year, 'month' => $previousMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Voriger Monat') }}" aria-label="{{ __('Voriger Monat') }}">‹</a>
                @else
                    <span class="p-1 text-gray-300">‹</span>
                @endif
                <label class="flex items-center gap-2 text-gray-700">{{ __('Monat') }}
                    <select name="month" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        @foreach (range(1, 12) as $itemMonth)
                            <option value="{{ $itemMonth }}" @selected($month === $itemMonth)>{{ \Carbon\CarbonImmutable::create(2000, $itemMonth, 1)->translatedFormat('F') }}</option>
                        @endforeach
                    </select>
                </label>
                @if ($nextMonth)
                    <a href="{{ $planningUrl(['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}" class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800" title="{{ __('Nächster Monat') }}" aria-label="{{ __('Nächster Monat') }}">›</a>
                @else
                    <span class="p-1 text-gray-300">›</span>
                @endif
                <a href="{{ $planningUrl(['year' => now()->year, 'month' => now()->month]) }}" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('heute') }}</a>
                </div>
            @endif
        </form>

        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'projektplanung-personen' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Personen') }} ({{ $selectedPersonIds->count() }})</button>
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'projektplanung-organisationen' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Organisationen') }} ({{ $selectedOrganizationIds->count() }})</button>
    </div>

    <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-max border-separate border-spacing-0 text-xs">
            <thead class="sticky top-0 z-10 isolate bg-gray-50 text-gray-700">
                @if ($displayMode === 'month')
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($dayWeekSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 bg-gray-50 px-1 py-0.5 text-center font-semibold">{{ __('KW') }} {{ $segment['week'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ __('Person') }}</th>
                        @foreach ($days as $day)
                            <th class="min-w-10 border-b border-r border-gray-200 px-1 py-1 text-center font-medium {{ $day->isToday() ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : 'bg-gray-50') }}" title="{{ $day->translatedFormat('l, d.m.Y') }}">
                                <span class="block text-[10px] text-gray-400">{{ $day->translatedFormat('D') }}</span>
                                <span class="block tabular-nums">{{ $day->format('d') }}</span>
                            </th>
                        @endforeach
                    </tr>
                @else
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50"></th>
                        @foreach ($weekMonthSegments as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 bg-gray-50 px-1 py-0.5 text-center font-semibold">{{ $segment['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-52 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ __('Person') }}</th>
                        @foreach ($weeks as $week)
                            <th class="min-w-12 border-b border-r border-gray-200 bg-gray-50 px-1 py-1 text-center font-medium" title="{{ $week['start']->format('d.m.Y') }}">{{ __('KW') }} {{ $week['number'] }}</th>
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
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-gray-50 px-2 py-1.5 text-left font-semibold text-gray-800">
                                {{ $person->last_name }}, {{ $person->first_name }}
                                @if ($contentMode === 'projects')
                                    <span class="ml-1 font-normal text-gray-400" title="{{ __(':count angezeigte Projekte', ['count' => $projectRowsByPerson->get($person->id, collect())->count()]) }}">({{ $projectRowsByPerson->get($person->id, collect())->count() }})</span>
                                @endif
                            </th>
                            <td colspan="{{ $displayMode === 'month' ? $days->count() : $weeks->count() }}"></td>
                        </tr>

                        @if ($contentMode === 'projects')
                            @forelse ($projectRowsByPerson->get($person->id, collect()) as $projectRow)
                                @php
                                    $project = $projectRow['project'];
                                    $hasPeriod = $project->start_date && $project->end_date && $project->start_date->lte($project->end_date);
                                    $tooltip = collect([
                                        $projectRow['label'],
                                        $projectRow['tenant']?->name,
                                        $projectRow['functionGroups'] ?: null,
                                        $projectRow['missingHours'] ? __('Planstunden noch nicht vollständig verteilt') : __(':hours geplante Stunden', ['hours' => number_format($projectRow['plannedHours'], 2, ',', '.')]),
                                    ])->filter()->join(' · ');
                                @endphp
                                <tr class="border-b border-gray-100">
                                    <td class="sticky left-0 z-[1] max-w-72 border-r border-gray-200 bg-white py-1 pl-5 pr-2">
                                        <div class="flex min-w-0 items-start gap-1.5">
                                            @if ($projectRow['tenant']?->icon_filename)
                                                <img src="{{ $projectRow['tenant']->iconUrl() }}" alt="{{ $projectRow['tenant']->name }}" title="{{ $projectRow['tenant']->name }}" class="mt-0.5 h-4 w-4 shrink-0 object-contain">
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    @if ($projectRow['canOpen'])
                                                        <a href="{{ route('projekte.show', $project) }}" onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $project->id }} } }))" class="min-w-0 flex-1 truncate text-blue-700 hover:underline" title="{{ $tooltip }}">{{ $projectRow['label'] }}</a>
                                                    @else
                                                        <span class="min-w-0 flex-1 truncate text-gray-600" title="{{ $tooltip }}">{{ $projectRow['label'] }}</span>
                                                    @endif
                                                    <span class="shrink-0 rounded bg-slate-100 px-1 py-px text-[10px] font-medium tabular-nums text-slate-600" title="{{ __('Für :name geplante Stunden', ['name' => $person->fullName()]) }}">
                                                        {{ number_format($projectRow['plannedHours'], 2, ',', '.') }} h
                                                        @if ($projectRow['missingHours'])
                                                            <span class="text-amber-600">+ {{ __('offen') }}</span>
                                                        @endif
                                                    </span>
                                                </div>
                                                <span class="block truncate text-[10px] text-gray-400">{{ $projectRow['tenant']?->name }}@if (! $hasPeriod) · {{ __('Zeitraum unvollständig') }}@endif</span>
                                            </div>
                                        </div>
                                    </td>
                                    @if ($displayMode === 'month')
                                        @foreach ($days as $day)
                                            @php
                                                $inside = $hasPeriod && $day->between($project->start_date, $project->end_date);
                                                $continuesBefore = $inside && $loop->first && $project->start_date->lt($rangeStart);
                                                $continuesAfter = $inside && $loop->last && $project->end_date->gt($rangeEnd);
                                                $cellMilestones = $projectRow['milestones']->where('date', $day->toDateString());
                                                $milestoneTooltip = $cellMilestones->map(fn ($milestone) => __('Meilenstein: :name (:date)', ['name' => $milestone['label'], 'date' => $day->format('d.m.Y')]))->join(' · ');
                                            @endphp
                                            <td class="relative h-6 border-r border-gray-100 p-0 {{ $day->isToday() ? 'bg-[#eff6ff]' : ($day->isWeekend() ? 'bg-[#fffaeb]' : '') }}" title="{{ $milestoneTooltip ?: ($inside ? $tooltip : '') }}">
                                                @if ($inside)
                                                    <div class="relative mx-0 flex h-3 items-center rounded-sm border border-black/10" style="background-color: {{ $projectRow['color'] }}">
                                                        @if ($continuesBefore)<span class="absolute left-0 text-[11px] font-bold leading-none text-gray-700" title="{{ __('Projekt beginnt vor dem angezeigten Zeitraum') }}">&lsaquo;</span>@endif
                                                        @if ($continuesAfter)<span class="absolute right-0 text-[11px] font-bold leading-none text-gray-700" title="{{ __('Projekt läuft nach dem angezeigten Zeitraum weiter') }}">&rsaquo;</span>@endif
                                                    </div>
                                                @endif
                                                @if ($cellMilestones->isNotEmpty())
                                                    <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center gap-0.5">
                                                        @foreach ($cellMilestones as $milestone)
                                                            <span class="pointer-events-auto block h-2 w-2 rotate-45 border border-white bg-fuchsia-600 shadow-sm" title="{{ __('Meilenstein: :name (:date)', ['name' => $milestone['label'], 'date' => $day->format('d.m.Y')]) }}"></span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    @else
                                        @foreach ($weeks as $week)
                                            @php
                                                $inside = $hasPeriod && $project->start_date->lte($week['start']->addDays(6)) && $project->end_date->gte($week['start']);
                                                $continuesBefore = $inside && $loop->first && $project->start_date->lt($rangeStart);
                                                $continuesAfter = $inside && $loop->last && $project->end_date->gt($rangeEnd);
                                                $weekEnd = $week['start']->addDays(6);
                                                $cellMilestones = $projectRow['milestones']->filter(fn ($milestone) => \Carbon\CarbonImmutable::parse($milestone['date'])->between($week['start'], $weekEnd));
                                                $milestoneTooltip = $cellMilestones->map(fn ($milestone) => __('Meilenstein: :name (:date)', ['name' => $milestone['label'], 'date' => \Carbon\CarbonImmutable::parse($milestone['date'])->format('d.m.Y')]))->join(' · ');
                                            @endphp
                                            <td class="relative h-6 border-r border-gray-100 p-0" title="{{ $milestoneTooltip ?: ($inside ? $tooltip : '') }}">
                                                @if ($inside)
                                                    <div class="relative flex h-3 items-center rounded-sm border border-black/10" style="background-color: {{ $projectRow['color'] }}">
                                                        @if ($continuesBefore)<span class="absolute left-0 text-[11px] font-bold leading-none text-gray-700" title="{{ __('Projekt beginnt vor dem angezeigten Zeitraum') }}">&lsaquo;</span>@endif
                                                        @if ($continuesAfter)<span class="absolute right-0 text-[11px] font-bold leading-none text-gray-700" title="{{ __('Projekt läuft nach dem angezeigten Zeitraum weiter') }}">&rsaquo;</span>@endif
                                                    </div>
                                                @endif
                                                @foreach ($cellMilestones as $milestone)
                                                    @php $milestoneDate = \Carbon\CarbonImmutable::parse($milestone['date']); @endphp
                                                    <span class="absolute top-1/2 z-10 block h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 border border-white bg-fuchsia-600 shadow-sm" style="left: {{ (($milestoneDate->isoWeekday() - 0.5) / 7) * 100 }}%" title="{{ __('Meilenstein: :name (:date)', ['name' => $milestone['label'], 'date' => $milestoneDate->format('d.m.Y')]) }}"></span>
                                                @endforeach
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @empty
                                <tr class="border-b border-gray-100"><td class="sticky left-0 bg-white py-1 pl-5 pr-2 text-gray-400">{{ __('Keine Projekte im Zeitraum') }}</td><td colspan="{{ $displayMode === 'month' ? $days->count() : $weeks->count() }}"></td></tr>
                            @endforelse
                        @else
                            @php
                                $metricLabels = [
                                    'work' => __('Arbeitszeit'),
                                    'absence' => __('Abwesenheit'),
                                    'base_load' => __('Grundlast'),
                                    'available' => __('Für Projekte verfügbar'),
                                    'project' => __('Geplante Projektstunden'),
                                    'remaining' => __('Restkapazität'),
                                    'utilization' => __('Auslastung %'),
                                ];
                                $personValues = $utilizationByPerson->get($person->id, collect());
                            @endphp
                            @foreach ($metricLabels as $metric => $metricLabel)
                                <tr class="border-b border-gray-100 {{ in_array($metric, ['available', 'remaining'], true) ? 'font-semibold' : '' }}">
                                    <td class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white py-1 pl-5 pr-2 text-gray-600">{{ $metricLabel }}</td>
                                    @foreach ($displayMode === 'month' ? $days : $weeks as $column)
                                        @php
                                            $key = $displayMode === 'month' ? $column->toDateString() : $column['key'];
                                            $values = $personValues->get($key, []);
                                            $value = $metric === 'utilization'
                                                ? (((float) ($values['available'] ?? 0)) > 0 ? (float) ($values['project'] ?? 0) / (float) $values['available'] * 100 : 0)
                                                : (float) ($values[$metric] ?? 0);
                                            $overloaded = in_array($metric, ['remaining', 'utilization'], true) && (float) ($values['remaining'] ?? 0) < -0.005;
                                        @endphp
                                        <td class="h-6 border-r border-gray-100 px-1 text-right tabular-nums {{ $overloaded ? 'bg-red-50 text-red-700' : '' }}">{{ $metric === 'utilization' ? number_format($value, 0, ',', '.').' %' : number_format($value, 2, ',', '.') }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                @empty
                    <tr><td colspan="{{ ($displayMode === 'month' ? $days->count() : $weeks->count()) + 1 }}" class="p-6 text-center text-gray-400">{{ __('Keine Personen ausgewählt.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal name="projektplanung-personen" max-width="md" :draggable="true">
        <form method="GET" action="{{ route('planung.projektplanung') }}" x-data="{ submitting: false }" @submit="submitting = true" :class="{ 'cursor-wait': submitting }" x-on:open-modal.window="if ($event.detail === 'projektplanung-personen') $nextTick(() => $refs.firstPerson?.focus())">
            <input type="hidden" name="view" value="{{ $displayMode }}">
            <input type="hidden" name="content" value="{{ $contentMode }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="person_filter" value="1">
            <input type="hidden" name="organization_filter" value="1">
            @foreach ($selectedOrganizationIds as $organizationId)
                <input type="hidden" name="organizations[]" value="{{ $organizationId }}">
            @endforeach
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
                <button type="submit" :disabled="submitting" class="inline-flex items-center gap-1.5 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-50">
                    <span x-show="submitting" x-cloak><x-loading-spinner class="h-3.5 w-3.5 text-white" /></span>
                    {{ __('Anwenden') }}
                </button>
            </div>
        </form>
    </x-modal>

    <x-modal name="projektplanung-organisationen" max-width="md" :draggable="true">
        <form method="GET" action="{{ route('planung.projektplanung') }}" x-data="{ submitting: false }" @submit="submitting = true" :class="{ 'cursor-wait': submitting }" x-on:open-modal.window="if ($event.detail === 'projektplanung-organisationen') $nextTick(() => $refs.firstOrganization?.focus())">
            <input type="hidden" name="view" value="{{ $displayMode }}">
            <input type="hidden" name="content" value="{{ $contentMode }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="person_filter" value="1">
            @foreach ($selectedPersonIds as $personId)
                <input type="hidden" name="people[]" value="{{ $personId }}">
            @endforeach
            <input type="hidden" name="organization_filter" value="1">
            <div data-drag-handle class="flex cursor-move select-none items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Organisationen auswählen') }}</h3>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektplanung-organisationen' }))" class="text-gray-400 hover:text-gray-600" aria-label="{{ __('Schließen') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
            </div>
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
                <button type="button" @click="$refs.organizationList.querySelectorAll('input[type=checkbox]').forEach((input) => input.checked = true)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Alle') }}</button>
                <button type="button" @click="$refs.organizationList.querySelectorAll('input[type=checkbox]').forEach((input) => input.checked = false)" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Keiner') }}</button>
            </div>
            <div x-ref="organizationList" class="max-h-[60vh] space-y-1 overflow-y-auto p-4 text-sm">
                @forelse ($organizations as $organization)
                    <label class="flex items-center gap-2 rounded px-1 py-1 hover:bg-gray-50">
                        <input @if ($loop->first) x-ref="firstOrganization" @endif type="checkbox" name="organizations[]" value="{{ $organization->id }}" @checked($selectedOrganizationIds->contains($organization->id)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>{{ $organization->name }}</span>
                    </label>
                @empty
                    <p class="text-gray-400">{{ __('Keine Organisationen verfügbar.') }}</p>
                @endforelse
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 p-3">
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektplanung-organisationen' }))" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                <button type="submit" :disabled="submitting" class="inline-flex items-center gap-1.5 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-wait disabled:opacity-50">
                    <span x-show="submitting" x-cloak><x-loading-spinner class="h-3.5 w-3.5 text-white" /></span>
                    {{ __('Anwenden') }}
                </button>
            </div>
        </form>
    </x-modal>
</x-planning-layout>
