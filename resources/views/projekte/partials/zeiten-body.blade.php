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
    deren Summe - "Lösen" trennt die Verbindung EINWEG (keine Rückkehr) und kopiert den
    aktuellen Schablonen-Stand JE FUNKTIONSGRUPPE hierher, ändert aber nie die Schablone
    selbst. Korrektur (Ralf, 2026-09-27, nachdem die erste Fassung nur einen einzigen
    Gesamt-Wert anbot): "dadurch habe ich keine Möglichkeit mehr, zu erkennen, aus welchen
    Stundenpaketen es sich rekrutiert" - ab "Lösen" bleibt die Aufschlüsselung nach
    Funktionsgruppe erhalten und unabhängig änderbar, gleiches Bearbeitungsmuster wie bei der
    Schablone selbst (admin/project-templates/partials/content.blade.php). Jedes Projekt (auch
    ein Unterprojekt) trägt seinen Plan unabhängig - im Verbund gilt implizit der Plan des
    Hauptprojekts für alle, solange kein Unterprojekt einen eigenen hat.
--}}
<div id="project-zeiten-body" class="text-sm">
    @php($fmt = fn ($hours) => number_format($hours, 2, ',', '.'))

    @if ($zeiten['ownPlanLinked'])
        <div
            x-data="{
                async loesen() {
                    if (! await window.confirmDialog({
                        title: {{ Illuminate\Support\Js::from(__('Verbindung zur Schablone lösen?')) }},
                        message: {{ Illuminate\Support\Js::from(__('Die Verbindung zur Schablone wird für dieses Projekt endgültig gelöst - eine spätere Rückkehr zur Schablonen-Verknüpfung ist nicht mehr möglich. Die Schablone selbst bleibt unverändert. Die aktuelle Aufschlüsselung je Funktionsgruppe wird als Startpunkt übernommen und bleibt danach unabhängig änderbar.')) }},
                        confirmLabel: {{ Illuminate\Support\Js::from(__('Lösen')) }},
                        cancelLabel: {{ Illuminate\Support\Js::from(__('Abbrechen')) }},
                    })) { return; }
                    const response = await fetch({{ Illuminate\Support\Js::from(route('projekte.planstunden.loesen', $project)) }}, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'text/html' },
                    });
                    if (! response.ok) {
                        await window.notifyDialog({{ Illuminate\Support\Js::from(__('Lösen fehlgeschlagen. Bitte erneut versuchen.')) }});
                        return;
                    }
                    document.getElementById('project-zeiten-body').outerHTML = await response.text();
                },
            }"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
        >
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Planstunden dieses Projekts') }}</span>
            <span class="font-semibold tabular-nums">{{ $fmt($zeiten['ownPlan']) }} h</span>
            <span class="text-gray-400">{{ __('(aus Schablone „:name")', ['name' => $zeiten['ownTemplateName']]) }}</span>
            <button
                type="button"
                @click="loesen()"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >
                {{ __('Lösen') }}
            </button>
        </div>
    @elseif ($zeiten['ownRelevantFunctionGroups']->isNotEmpty() || $zeiten['ownBreakdown']->isNotEmpty())
        <form
            x-data="{
                dirty: false,
                hours: {{ Illuminate\Support\Js::from($zeiten['ownBreakdown']) }},
                async save() {
                    const params = new URLSearchParams();
                    Object.entries(this.hours).forEach(([id, val]) => params.append('hours[' + id + ']', val ?? ''));
                    const response = await fetch({{ Illuminate\Support\Js::from(route('projekte.planstunden', $project)) }}, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'text/html' },
                        body: params,
                    });
                    if (! response.ok) {
                        await window.notifyDialog({{ Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte erneut versuchen.')) }});
                        return;
                    }
                    document.getElementById('project-zeiten-body').outerHTML = await response.text();
                },
            }"
            @input="dirty = window.formIsDirty($el)"
            @submit.prevent="save()"
            class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Planstunden je Funktionsgruppe') }}</p>
                <p class="text-xs text-gray-400">
                    {{ __('Summe') }}: <span x-text="Object.values(hours).reduce((sum, v) => sum + (parseFloat(v) || 0), 0).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })"></span> h
                </p>
            </div>
            <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($zeiten['ownRelevantFunctionGroups'] as $fg)
                    <label class="flex items-center justify-between gap-1 rounded-md border border-gray-200 bg-white px-1.5 py-1 text-xs text-gray-600">
                        <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                        <input type="number" name="hours[{{ $fg->id }}]" x-model="hours['{{ $fg->id }}']" min="0" max="999" step="0.5" placeholder="–" class="w-16 shrink-0 rounded-md border-gray-300 py-0.5 text-xs">
                    </label>
                @endforeach
            </div>
            <div class="mt-1 flex justify-end">
                <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                    {{ __('Speichern') }}
                </button>
            </div>
        </form>
    @else
        <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
            {{ __('Kein Plan hinterlegt') }}
        </div>
    @endif

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
