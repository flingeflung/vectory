{{--
    Reiter "Zeiten" (Ralf, 2026-09-27, siehe Roadmap-Backlog): Überblick über die am Projekt
    gebuchten Stunden - am Hauptprojekt zusätzlich je Unterprojekt aufgeschlüsselt. Gleiche
    Tabellen-Optik wie "Nach Jobgruppen" in der Zeiterfassungs-Übersicht
    (resources/views/jobload/overview.blade.php). Aggregiert über ALLE Personen
    (Projektleiter-Sicht), nicht nur die eigenen Buchungen wie im Zeiterfassung-Overlay.

    Eigenes Partial (statt direkt in detail.blade.php), weil sich der Planstunden-Editor unten
    per fetch() selbst neu lädt (Konvention wie project-hours-body etc.) - der Rest des
    Projekt-Reiter-Systems bleibt inline/x-show, nur dieser eine Block tauscht sich per JS aus.

    Planstunden (Ralf, 2026-09-27): solange mit der Aufwandsschablone verknüpft, gilt live
    deren Summe - "Lösen" trennt die Verbindung EINWEG (keine Rückkehr) und macht den Wert
    frei editierbar, ändert aber nie die Schablone selbst. Jedes Projekt (auch ein
    Unterprojekt) trägt seinen Plan unabhängig - im Verbund gilt implizit der Plan des
    Hauptprojekts für alle, solange kein Unterprojekt einen eigenen hat.
--}}
<div id="project-zeiten-body" class="text-sm">
    @php($fmt = fn ($hours) => number_format($hours, 2, ',', '.'))

    <div
        x-data="{
            editing: false,
            value: {{ $zeiten['ownPlan'] !== null ? number_format($zeiten['ownPlan'], 2, '.', '') : '0' }},
            async breakLink() {
                if (! await window.confirmDialog({
                    title: {{ Illuminate\Support\Js::from(__('Verbindung zur Schablone lösen?')) }},
                    message: {{ Illuminate\Support\Js::from(__('Die Verbindung zur Schablone wird für dieses Projekt endgültig gelöst - eine spätere Rückkehr zur Schablonen-Verknüpfung ist nicht mehr möglich. Die Schablone selbst bleibt unverändert.')) }},
                    confirmLabel: {{ Illuminate\Support\Js::from(__('Lösen')) }},
                    cancelLabel: {{ Illuminate\Support\Js::from(__('Abbrechen')) }},
                })) { return; }
                this.editing = true;
            },
            async save() {
                const response = await fetch({{ Illuminate\Support\Js::from(route('projekte.planstunden', $project)) }}, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'text/html' },
                    body: new URLSearchParams({ hours: this.value }),
                });
                if (! response.ok) {
                    await window.notifyDialog({{ Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte erneut versuchen.')) }});
                    return;
                }
                document.getElementById('project-zeiten-body').outerHTML = await response.text();
            },
        }"
        class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
    >
        <div class="flex flex-wrap items-center gap-2" x-show="! editing">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Planstunden dieses Projekts') }}</span>
            @if ($zeiten['ownPlan'] !== null)
                <span class="font-semibold tabular-nums">{{ $fmt($zeiten['ownPlan']) }} h</span>
                @if ($zeiten['ownPlanLinked'])
                    <span class="text-gray-400">{{ __('(aus Schablone „:name")', ['name' => $zeiten['ownTemplateName']]) }}</span>
                    <button type="button" @click="breakLink()" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Lösen') }}
                    </button>
                @else
                    <span class="text-gray-400">{{ __('(eigener Wert)') }}</span>
                    <button type="button" @click="editing = true" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Ändern') }}
                    </button>
                @endif
            @else
                <span class="text-gray-500">{{ __('Kein Plan hinterlegt') }}</span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2" x-show="editing" x-cloak>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Planstunden dieses Projekts') }}</label>
            <input type="number" step="0.25" min="0" max="9999.99" x-model.number="value" class="w-24 rounded border-gray-300 text-sm tabular-nums">
            <span class="text-xs text-gray-500">h</span>
            <button type="button" @click="save()" class="rounded-md bg-btn-primary px-2 py-0.5 text-xs font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
            <button type="button" @click="editing = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
        </div>
    </div>

    @if ($zeiten['planTotal'] !== null)
        <p class="mb-4 text-xs text-gray-500">
            {{ __(':ist h von :plan h geplant – :percent %', [
                'ist' => $fmt($zeiten['total']),
                'plan' => $fmt($zeiten['planTotal']),
                'percent' => number_format($zeiten['planTotal'] > 0 ? $zeiten['total'] / $zeiten['planTotal'] * 100 : 0, 0, ',', '.'),
            ]) }}
        </p>
    @endif

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
                                <span class="text-gray-400">– {{ $row['title'] }}</span>
                                @if ($row['isHauptprojekt'])
                                    <span class="ml-1 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700">{{ __('HP') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-1 whitespace-nowrap text-right tabular-nums text-gray-500">{{ $row['plan'] !== null ? $fmt($row['plan']) : '–' }}</td>
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
