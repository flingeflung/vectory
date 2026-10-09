{{--
    Planung > Stunden (Ralf, 2026-09-28) - Jahresstunden-Kapazität je Person,
    Grundlage der personellen Ressourcenplanung. Berechnung aus Wochenstunden-
    und Urlaubstage-Historie (siehe PlanningController::stunden()) - beide
    können sich unterjährig ändern, die Jahresstunden werden deshalb
    abschnittsweise berechnet statt mit einem einzelnen WoSt-Wert
    hochgerechnet.
--}}
<x-planning-layout>
    @php($fmt = fn ($value) => number_format($value, 1, ',', '.'))
    <nav class="mb-2 flex shrink-0 gap-1 border-b border-gray-200" aria-label="{{ __('Ansicht') }}">
        @foreach (['tabelle' => __('Tabelle'), 'grafik' => __('Grafik'), 'soll-ist' => __('Soll-Ist')] as $key => $label)
            <a href="{{ request()->url().'?'.http_build_query(array_merge(request()->query(), ['ansicht' => $key])) }}"
               class="border-b-2 px-3 py-1.5 text-sm font-medium {{ $view === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('planung.stunden') }}" class="mb-3 flex shrink-0 items-center gap-2 text-sm">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <input type="hidden" name="ansicht" value="{{ $view }}">
        <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
            <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </label>
        @if ($view === 'soll-ist')
            <label class="flex items-center gap-2 text-gray-700" title="{{ __('Gerechnet wird nur ab diesem Tag. Standard ist heute; so lässt sich auch ab einem späteren Datum vorausplanen.') }}">{{ __('Ab') }}
                <input type="date" name="ab" value="{{ $cutoff->toDateString() }}" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
            </label>
        @endif
    </form>
    {{-- Ralf, 2026-09-29: Abteilungsfilter ersatzlos entfernt (funktionierte
         nicht zuverlässig für alle Szenarien) - welche Personen hier
         auftauchen, wird jetzt direkt je Person in den Personendetails
         gepflegt (Checkbox "Ressourcenplanung"). --}}

    @if ($view === 'grafik')
        @include('planning.partials.stunden-grafik')
    @elseif ($view === 'soll-ist')
        @include('planning.partials.stunden-sollist')
    @else
    <div id="planning-stunden-content" class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
                <tr class="border-b border-gray-200">
                    <x-sortable-th field="name" :sort="$sort" :direction="$direction" :compact="true">{{ __('Name') }}</x-sortable-th>
                    <x-sortable-th field="department" :sort="$sort" :direction="$direction" :compact="true">{{ __('Abteilung') }}</x-sortable-th>
                    <x-sortable-th field="annotation" :sort="$sort" :direction="$direction" :compact="true"><span title="{{ __('Anmerkungen') }}">{{ __('Anm.') }}</span></x-sortable-th>
                    <x-sortable-th field="wost" :sort="$sort" :direction="$direction" align="right" :compact="true"><span title="{{ __('Die Wochenstunden werden in den Personendetails gepflegt.') }}">{{ __('WoStd') }}</span></x-sortable-th>
                    <x-sortable-th field="workdays" :sort="$sort" :direction="$direction" align="right" :compact="true">
                        <span title="{{ __('Reine Wochentage (Mo-Fr) des Jahres - ohne Feiertage oder Krankheitstage abzuziehen.') }}">{{ __('Arbeitstage') }}</span>
                    </x-sortable-th>
                    <x-sortable-th field="holidays" :sort="$sort" :direction="$direction" align="right" :compact="true">
                        <span title="{{ __('Aktive Feiertage von Montag bis Freitag im Beschäftigungszeitraum.') }}">{{ __('Feiertage') }}</span>
                    </x-sortable-th>
                    <x-sortable-th field="vacation_hours" :sort="$sort" :direction="$direction" align="right" :compact="true">
                        <span title="{{ __('Anteilig berechneter Urlaubsanspruch, umgerechnet in Stunden.') }}">{{ __('Urlaub (Std)') }}</span>
                    </x-sortable-th>
                    <x-sortable-th field="annual_hours" :sort="$sort" :direction="$direction" align="right" :compact="true"><span title="{{ __('Arbeitszeit des Jahres nach Abzug von Feiertagen und Urlaub.') }}">{{ __('Jahresstd.') }}</span></x-sortable-th>
                    <x-sortable-th field="base_load" :sort="$sort" :direction="$direction" align="right" :compact="true"><span title="{{ __('Stunden, die für Aufgaben außerhalb von Projekten reserviert sind. Die Werte stehen unter Planung › Grundlastbasis und Grundlast pro Person.') }}">{{ __('Grundlast') }}</span></x-sortable-th>
                    <x-sortable-th field="project_hours" :sort="$sort" :direction="$direction" align="right" :compact="true">
                        <span title="{{ __('Stunden, die für Projekte zur Verfügung stehen') }}">{{ __('Projektstd.') }}</span>
                    </x-sortable-th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-3 py-1.5">
                            <div class="flex items-center gap-2">
                                <x-edit-icon-button
                                    :title="__('Personendetails bearbeiten')"
                                    onclick="window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $row['personId'] }} } }))"
                                />
                                <span>{{ $row['lastName'] }}, {{ $row['firstName'] }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-1.5 text-gray-500">{{ $row['department'] ?: '–' }}</td>
                        <td class="px-3 py-1.5 text-xs whitespace-nowrap">
                            @if ($row['inactive'])<span class="font-medium text-red-600">{{ __('Inaktiv') }}</span>@endif
                            @if ($row['inactive'] && $row['annotation'] !== ''), @endif
                            @if ($row['annotation'] !== '')<span class="text-gray-500">{{ $row['annotation'] }}</span>@elseif (! $row['inactive'])<span class="text-gray-400">–</span>@endif
                        </td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($row['wost']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $row['workdays'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $row['holidays'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($row['vacationHours']) }}</td>
                        <td class="px-3 py-1.5 text-right font-medium tabular-nums">{{ $fmt($row['jahresstd']) }}</td>
                        <td class="px-3 py-1.5 text-right font-medium tabular-nums">{{ $fmt($row['baseLoad']) }}</td>
                        <td class="px-3 py-1.5 text-right font-medium tabular-nums">{{ $fmt($row['projectHours']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">{{ __('Für :year sind keine Personen sichtbar - entweder ist bei niemandem "Ressourcenplanung" angehakt, oder es fehlen gültige Wochenstunden-Daten für dieses Jahr.', ['year' => $year]) }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr class="border-t-2 border-gray-500">
                        <td class="px-3 py-2" colspan="7">{{ __('Summe') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($total) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($baseLoadTotal) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($projectHoursTotal) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    @endif

    <script>
        // Ralf, 2026-09-29: nach dem Bearbeiten einer Person im globalen
        // Personen-Overlay (siehe oben) die Tabelle hier automatisch
        // nachziehen, ohne die ganze Seite neu zu laden - z.B. wenn die
        // Wochenstunden gerade korrigiert wurden.
        window.addEventListener('close-modal', async (event) => {
            if (event.detail !== 'person-overlay') {
                return;
            }
            const container = document.getElementById('planning-stunden-content');
            if (!container) {
                return;
            }
            const html = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } }).then((r) => r.text());
            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('planning-stunden-content');
            if (fresh) {
                container.outerHTML = fresh.outerHTML;
            }
        });
    </script>
</x-planning-layout>
