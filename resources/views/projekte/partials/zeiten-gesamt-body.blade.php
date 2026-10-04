{{--
    Zeiten-Tab, Unterreiter "Gesamtansicht" (Ralf, 2026-09-28) - Nachbau der
    Zeiterfassungs-Übersicht (resources/views/jobload/overview.blade.php, Modi
    "Nach Personen"/"Nach Jobs"), aber auf die Jobs der beteiligten Projekte
    (HP+UP) eingeschränkt und über deren Laufzeit (frühestes Start- bis spätestes
    Enddatum) statt über ein Kalenderjahr, mit Kalenderwochen als Spalten. Gleiches
    Personenbezogene Modi sind durch planning.view geschützt (siehe
    zeiten-body.blade.php). Dritter, hier eigener Modus "Nach Projekten" (Ralf,
    2026-09-28) - ergibt nur im Projekt-Kontext Sinn, deshalb nicht im Hauptmenü.
    "– Alle –" im Personen-Dropdown schlüsselt stattdessen nach Personen auf
    (spiegelbildlich zu "Alle Jobs" im Modus "Jobs").
--}}
{{-- Ralf, 2026-09-28: "wenn ich oben blättere, lande ich immer auf Nach Personen" - jedes Projekt rendert die
     Gesamtansicht serverseitig zunächst mit dem sicheren Standard-Modus "person" (siehe zeitenData()); ein zuvor
     bewusst gewählter anderer Modus (gleiches window.*-Muster wie window.projectZeitenSubTab) wird im x-init unten
     sofort per Reload nachgezogen. Kein Kommentar im x-init selbst: Alpine verträgt dort keine Zeilenkommentare am Anfang. --}}
<div id="project-zeiten-gesamt-body">
    <div
        class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs"
        x-data="{
            reload(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('projekte.zeiten.gesamt', $project)) }}, window.location.origin);
                Object.entries({ mode: {{ \Illuminate\Support\Js::from($gesamt['mode'] ?? 'person') }}, person_id: {{ \Illuminate\Support\Js::from($gesamt['personId'] ?? '') }}, job_id: {{ \Illuminate\Support\Js::from($gesamt['jobId'] ?? '') }}, show_inactive: {{ \Illuminate\Support\Js::from(($gesamt['showInactive'] ?? false) ? '1' : '') }}, ...params }).forEach(([key, value]) => { url.searchParams.set(key, value === null ? '' : value); });
                fetch(url, { headers: { 'Accept': 'text/html' } }).then((response) => response.text()).then((html) => { document.getElementById('project-zeiten-gesamt-body').outerHTML = html; });
            },
        }"
        x-init="
            if (window.projectZeitenGesamtMode && window.projectZeitenGesamtMode !== {{ \Illuminate\Support\Js::from($gesamt['mode'] ?? 'person') }}) {
                reload({ mode: window.projectZeitenGesamtMode });
            }
        "
    >
        @if ($gesamt['hasRange'] ?? false)
            <fieldset class="flex items-center gap-3">
                <legend class="sr-only">{{ __('Darstellung') }}</legend>
                <label class="flex items-center gap-1.5"><input type="radio" name="gesamt-mode" @checked($gesamt['mode'] === 'project') @click="window.projectZeitenGesamtMode = 'project'; reload({ mode: 'project' })" class="border-gray-300 text-btn-primary">{{ __('Nach Projekten') }}</label>
                @if ($canViewPeople)
                    <label class="flex items-center gap-1.5"><input type="radio" name="gesamt-mode" @checked($gesamt['mode'] === 'person') @click="window.projectZeitenGesamtMode = 'person'; reload({ mode: 'person' })" class="border-gray-300 text-btn-primary">{{ __('Nach Personen') }}</label>
                @endif
                <label class="flex items-center gap-1.5"><input type="radio" name="gesamt-mode" @checked($gesamt['mode'] === 'job') @click="window.projectZeitenGesamtMode = 'job'; reload({ mode: 'job' })" class="border-gray-300 text-btn-primary">{{ __('Nach Jobs') }}</label>
            </fieldset>
            @if ($gesamt['mode'] === 'person' && $canViewPeople)
                <label class="flex items-center gap-2 text-gray-700">{{ __('Person') }}
                    <select @change="reload({ person_id: $event.target.value })" class="max-w-64 rounded-md border-gray-300 py-1 text-xs">
                        <option value="" @selected($gesamt['personId'] === null)>{{ __('– Alle –') }}</option>
                        @foreach ($gesamt['people'] as $person)
                            <option value="{{ $person->id }}" @selected($gesamt['personId'] === $person->id) @class(['text-gray-400' => ! $person->active])>{{ $person->last_name }}, {{ $person->first_name }}{{ ! $person->active ? ' [i]' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-1.5 text-gray-600">
                    <input type="checkbox" @checked($gesamt['showInactive']) @change="reload({ show_inactive: $event.target.checked ? '1' : '' })" class="rounded border-gray-300">
                    {{ __('Inaktive zeigen') }}
                </label>
            @elseif ($gesamt['mode'] === 'job' && $canViewPeople)
                <label class="flex items-center gap-2 text-gray-700">{{ __('Job') }}
                    <select @change="reload({ job_id: $event.target.value })" class="max-w-72 rounded-md border-gray-300 py-1 text-xs">
                        <option value="" @selected($gesamt['jobId'] === null)>{{ __('Alle Jobs') }}</option>
                        @foreach ($gesamt['jobs'] as $job)
                            <option value="{{ $job->id }}" @selected($gesamt['jobId'] === $job->id)>{{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        @endif
    </div>

    @if (! ($gesamt['hasRange'] ?? false))
        <p class="text-gray-500">{{ __('Für die beteiligten Projekte ist kein Start-/Enddatum hinterlegt - die Gesamtansicht braucht beides, um den Zeitraum zu bestimmen.') }}</p>
    @else
        @php
            $fmt = fn ($hours) => number_format($hours, $gesamt['hourDecimals'], ',', '.');
            $rowHeaderLabel = match ($gesamt['rowKind']) {
                'project' => __('Projekt'),
                'person' => __('Person'),
                'job' => __('Job'),
            };
        @endphp
        <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-max border-collapse text-xs">
                <thead class="sticky top-0 z-10 bg-gray-50 text-gray-700">
                    <tr>
                        {{-- Ralf, 2026-09-28: Start-/Enddatum direkt an der Tabelle statt oben in
                             der Filterleiste - Start in der (sticky) linken Ecke, Ende ganz hinten
                             nach dem letzten Monat, damit beide erkennbar zum abgebildeten
                             Zeitraum gehören. --}}
                        <th colspan="2" class="sticky left-0 z-20 border-b border-r border-gray-200 bg-gray-50 px-2 text-left font-normal text-gray-400" title="{{ __('Frühestes Startdatum der beteiligten Projekte') }}">{{ $gesamt['weeks']->first()['start']->format('d.m.Y') }}</th>
                        @foreach ($gesamt['monthSegments'] as $segment)
                            <th colspan="{{ $segment['count'] }}" class="border-b border-r border-gray-200 px-1 py-0.5 text-center font-semibold">{{ $segment['label'] }}</th>
                        @endforeach
                        <th class="border-b border-gray-200 px-2 text-right font-normal text-gray-400" title="{{ __('Spätestes Enddatum der beteiligten Projekte') }}">{{ $gesamt['weeks']->last()['end']->format('d.m.Y') }}</th>
                    </tr>
                    <tr>
                        <th class="sticky left-0 z-20 min-w-44 border-b border-r border-gray-200 bg-gray-50 px-2 py-1 text-left font-medium">{{ $rowHeaderLabel }}</th>
                        <th class="border-b border-r border-gray-200 px-2 py-1 text-right font-medium">{{ __('Ges.') }}</th>
                        @foreach ($gesamt['weeks'] as $week)
                            <th class="min-w-16 border-b border-r border-gray-200 px-1 py-1 text-center font-medium" title="{{ $week['start']->format('d.m.Y') }} – {{ $week['end']->format('d.m.Y') }}">
                                {{ __('KW') }} {{ $week['number'] }}
                            </th>
                        @endforeach
                        <th class="border-b border-gray-200"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gesamt['rows'] as $row)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white px-2 py-1 text-left font-normal text-gray-800">
                                @if ($gesamt['rowKind'] === 'project' && $row['id'] !== $project->id)
                                    {{-- Ralf, 2026-09-28: direkt zum Projekt wechseln können - gleiches
                                         Muster wie andere "Zu Projekt springen"-Links (z.B.
                                         system-fields/project_connections.blade.php). Das gerade
                                         angezeigte Projekt selbst bleibt unverlinkt (kein Sprung ins
                                         eigene, schon offene Overlay). --}}
                                    <a href="{{ route('projekte.show', $row['id']) }}" onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $row['id'] }} } }))" class="text-indigo-600 hover:underline">{{ $row['label'] }}</a>
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </th>
                            <td class="border-r border-gray-200 px-2 py-1 text-right font-semibold tabular-nums">{{ $fmt($row['total']) }}</td>
                            @foreach ($gesamt['weeks'] as $week)
                                @php($milestones = $gesamt['milestonesByProjectWeek'][$row['id']][$week['key']] ?? null)
                                <td class="border-r border-gray-100 px-1 py-1 text-center tabular-nums">
                                    <span>{{ ($row['weeks'][$week['key']] ?? 0) > 0 ? $fmt($row['weeks'][$week['key']]) : '' }}</span>
                                    @if ($milestones)
                                        {{-- Ralf, 2026-09-28: kleiner Punkt je KW mit Workflow-Termin(en) -
                                             nur im Modus "Nach Projekten", Tooltip nennt Datum + Terminbezeichnung. --}}
                                        <span
                                            class="ml-0.5 inline-block h-1.5 w-1.5 rounded-full bg-indigo-500 align-middle"
                                            title="{{ collect($milestones)->map(fn ($m) => $m['date'].' – '.$m['title'])->implode("\n") }}"
                                        ></span>
                                    @endif
                                </td>
                            @endforeach
                            <td></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $gesamt['weeks']->count() + 3 }}" class="px-4 py-8 text-center text-gray-500">{{ __('Keine Stunden im gewählten Zeitraum erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr>
                        <th class="sticky left-0 z-[1] border-r border-gray-200 bg-gray-50 px-2 py-1 text-left">{{ __('Summe') }}</th>
                        <td class="border-r border-gray-200 px-2 py-1 text-right tabular-nums">{{ $fmt($gesamt['total']) }}</td>
                        @foreach ($gesamt['weeks'] as $week)
                            <td class="border-r border-gray-200 px-1 py-1 text-center tabular-nums">{{ $gesamt['weekTotals'][$week['key']] > 0 ? $fmt($gesamt['weekTotals'][$week['key']]) : '' }}</td>
                        @endforeach
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
