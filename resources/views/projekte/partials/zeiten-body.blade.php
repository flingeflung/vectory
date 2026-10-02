{{--
    Reiter "Zeiten" (Ralf, 2026-09-27, siehe Roadmap-Backlog): Überblick über die am Projekt
    gebuchten Stunden - am Hauptprojekt zusätzlich je Unterprojekt aufgeschlüsselt. Gleiche
    Tabellen-Optik wie "Nach Jobgruppen" in der Zeiterfassungs-Übersicht
    (resources/views/jobload/overview.blade.php). Aggregiert über ALLE Personen
    (Projektleiter-Sicht), nicht nur die eigenen Buchungen wie im Zeiterfassung-Overlay.

    Die Planstunden werden hier nur noch kompakt den gebuchten Stunden gegenübergestellt.
    Bearbeitung, Aufschlüsselung und das Lösen von der Aufwandsschablone liegen im Reiter
    "Planung" (planned-hours-editor.blade.php).
--}}
{{-- Ralf, 2026-09-28: "wenn ich oben blättere, soll der Sub-Reiter bestehen
     bleiben" - gleiches Muster wie window.projectOverlayActiveTab für den
     Haupt-Reiter (detail.blade.php): globale Variable statt nur lokalem
     Alpine-State, da ein Projektwechsel dieses Fragment komplett neu rendert
     (frischer x-data-Start), eine normale Alpine-Persistenz also nichts
     nützt. --}}
<div
    id="project-zeiten-body"
    class="text-sm"
    x-data="{ subTab: (window.projectZeitenSubTab && ({{ \Illuminate\Support\Js::from(auth()->user()->can('planning.view')) }} || window.projectZeitenSubTab !== 'personen')) ? window.projectZeitenSubTab : 'uebersicht' }"
    x-init="$watch('subTab', value => window.projectZeitenSubTab = value)"
>
    @php($fmt = fn ($hours) => number_format($hours, 2, ',', '.'))

    {{-- Unterreiter "Nach Person & Tag" (Ralf, 2026-09-28) - nur sichtbar mit eigenem
         Recht planning.view (personenbezogen, siehe
         ProjectController::zeitenPersonBreakdown()). Wer das Recht nicht hat, sieht
         gar keinen Hinweis auf diesen Unterreiter - bewusst kein gesperrtes/gegrautes
         Element (Leistungskontrolle-Sensibilität, siehe Rechtekonzept-Diskussion). --}}
        <div class="mb-3 flex gap-1 border-b border-gray-100">
            <button type="button" @click="subTab = 'uebersicht'" :class="subTab === 'uebersicht' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-2 pb-1.5 text-xs font-medium">{{ __('Projektstunden') }}</button>
            @can('planning.view')
            <button type="button" @click="subTab = 'personen'" :class="subTab === 'personen' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-2 pb-1.5 text-xs font-medium">{{ __('Personen & Tage') }}</button>
            @endcan
            <button type="button" @click="subTab = 'gesamt'" :class="subTab === 'gesamt' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-2 pb-1.5 text-xs font-medium">{{ __('Zeitverlauf') }}</button>
        </div>

    <div x-show="subTab === 'uebersicht'">
        <p class="mb-1 text-[11px] text-gray-400">
            @if ($project->verbund_rolle === 1)
                {{ __('Bezieht sich auf das Hauptprojekt „:project“ und alle zugehörigen Unterprojekte.', ['project' => $project->source_pn.' – '.$project->title]) }}
            @else
                {{ __('Bezieht sich auf Projekt „:project“.', ['project' => $project->source_pn.' – '.$project->title]) }}
            @endif
        </p>
        <div
            x-data="{ plan: {{ Illuminate\Support\Js::from($zeiten['planTotal']) }} }"
            @project-planned-hours-changed.window="plan = $event.detail"
            class="mb-4 grid max-w-2xl grid-cols-3 gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs"
        >
            <div>
                <div class="text-gray-400">{{ __('Geplante Stunden') }}</div>
                <div class="font-semibold tabular-nums text-gray-900" x-text="plan === null ? '–' : Number(plan).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' h'"></div>
            </div>
            <div>
                <div class="text-gray-400">{{ __('Gebuchte Stunden') }}</div>
                <div class="font-semibold tabular-nums text-gray-900">{{ $fmt($zeiten['total']) }} h</div>
            </div>
            <div>
                <div class="text-gray-400">{{ __('Differenz') }}</div>
                <div class="font-semibold tabular-nums text-gray-900" x-text="plan === null ? '–' : (Number(plan) - {{ Illuminate\Support\Js::from($zeiten['total']) }}).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' h'"></div>
            </div>
        </div>
    @if ($zeiten['total'] <= 0)
        <p class="text-gray-500">{{ __('Für dieses Projekt sind noch keine Stunden gebucht.') }}</p>
    @else
        @if ($zeiten['isHauptprojekt'])
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Je Projekt') }}</h3>
            <table class="mb-6 w-full max-w-md">
                <thead>
                    <tr class="border-b border-gray-200 text-xs text-gray-500">
                        <th class="py-1 pr-3 text-left font-medium">{{ __('Projekt') }}</th>
                        <th class="px-3 py-1 text-right font-medium">{{ __('Plan') }}</th>
                        <th class="py-1 pl-3 text-right font-medium">{{ __('Ist') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($zeiten['perProject'] as $row)
                        <tr class="border-b border-gray-100">
                            <td class="py-1 pr-3">
                                <span class="{{ $row['isHauptprojekt'] ? 'font-semibold text-indigo-700' : '' }}">{{ $row['label'] }}</span>
                                @if ($row['isHauptprojekt'])
                                    {{-- Ralf, 2026-09-28: "dieselbe Fahne wie in der Übersicht, mit
                                         derselben Caption" - statt eigener "HP"-Pille dieselbe Fahne
                                         wie x-hauptprojekt-icon (rows.blade.php/detail.blade.php).
                                         $row ist hier ein Array, kein Project-Model, deshalb direkt
                                         markiert statt über die Komponente (die $project->verbund_rolle
                                         prüft). --}}
                                    <span class="ml-1 inline-block align-middle text-indigo-600" title="{{ __('Hauptprojekt eines Verbunds') }}">⚑</span>
                                @endif
                                <span class="text-gray-400">– {{ $row['title'] }}</span>
                            </td>
                            {{-- Ralf, 2026-09-27: "da ist farblich wenig Unterschied zu erkennen
                                 zwischen den Stunden, die noch nach Schablone sind und denen, die
                                 schon gelöst sind" - eigener (gelöster) Wert jetzt amber + eigenes
                                 Symbol, Schablonen-Wert bleibt neutral grau. --}}
                            <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums {{ $row['plan'] === null ? 'text-gray-400' : ($row['planLinked'] ? 'text-gray-500' : 'text-amber-700') }}">
                                @if ($row['plan'] !== null && ! $row['planLinked'])
                                    <span title="{{ __('Eigener Wert - nicht mehr mit der Schablone verbunden') }}" class="mr-0.5 inline-block h-1.5 w-1.5 rounded-full bg-amber-500 align-middle"></span>
                                @endif
                                {{ $row['plan'] !== null ? $fmt($row['plan']) : '–' }}
                            </td>
                            <td class="py-1 pl-3 whitespace-nowrap text-right tabular-nums">{{ $fmt($row['hours']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold text-gray-800">
                        <td class="py-1 pr-3">{{ __('Summe') }}</td>
                        <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums">{{ $zeiten['planTotal'] !== null ? $fmt($zeiten['planTotal']) : '–' }}</td>
                        <td class="py-1 pl-3 whitespace-nowrap text-right tabular-nums">{{ $fmt($zeiten['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
            @if ($zeiten['perProject']->contains(fn ($row) => $row['plan'] !== null && ! $row['planLinked']))
                <p class="mb-4 -mt-4 flex items-center gap-1 text-[11px] text-gray-400">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    {{ __('Eigener Wert, nicht mehr mit der Schablone verbunden') }}
                </p>
            @endif
        @endif

        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Nach Job') }}</h3>
        <table class="w-full max-w-md">
            <thead>
                <tr class="border-b border-gray-200 text-xs text-gray-500">
                    <th class="py-1 pr-3 text-left font-medium">{{ __('Job') }}</th>
                    <th class="px-3 py-1 text-right font-medium">{{ __('Stunden') }}</th>
                    <th class="py-1 pl-3 text-right font-medium">{{ __('Anteil') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($zeiten['byJob'] as $item)
                    <tr class="border-b border-gray-100">
                        <td class="py-1 pr-3">{{ $item['label'] }}</td>
                        <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums">{{ $fmt($item['hours']) }}</td>
                        <td class="py-1 pl-3 whitespace-nowrap text-right tabular-nums">{{ number_format($item['percent'], 1, ',', '.') }} %</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold text-gray-800">
                    <td class="py-1 pr-3">{{ __('Summe') }}</td>
                    <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums">{{ $fmt($zeiten['total']) }}</td>
                    <td class="py-1 pl-3 whitespace-nowrap text-right tabular-nums">100,0 %</td>
                </tr>
            </tfoot>
        </table>
    @endif
    </div>

    @can('planning.view')
        <div x-show="subTab === 'personen'" x-cloak>
            @include('projekte.partials.zeiten-personen-body', ['week' => $zeiten['personBreakdownWeek'], 'sortBy' => $zeiten['personBreakdownSort'], 'breakdown' => $zeiten['personBreakdown']])
        </div>
    @endcan
    <div x-show="subTab === 'gesamt'" x-cloak>
        @include('projekte.partials.zeiten-gesamt-body', ['gesamt' => $zeiten['gesamtansicht'], 'canViewPeople' => auth()->user()->can('planning.view')])
    </div>
</div>
