<div
    id="project-planned-hours-editor"
    data-plan-total="{{ $zeiten['planTotal'] ?? '' }}"
    class="shrink-0"
>
@php($fmt = fn ($hours) => number_format($hours, 2, ',', '.'))
@php($canManagePlanning = auth()->user()->can('planning.view'))
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
                document.getElementById('project-planned-hours-editor').outerHTML = await response.text();
                const planTotal = document.getElementById('project-planned-hours-editor').dataset.planTotal;
                window.dispatchEvent(new CustomEvent('project-planned-hours-changed', { detail: planTotal === '' ? null : Number(planTotal) }));
                // Ralf-Bug-Report, 2026-09-28: Details-Tab (Schloss am Schablonen-Feld)
                // bekam das Lösen sonst nicht mit, da nur dieser Block hier getauscht wird -
                // siehe project_template.blade.php.
                window.dispatchEvent(new CustomEvent('planstunden-linked-state-changed'));
            },
        }"
        class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
    >
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Planstunden dieses Projekts') }}</span>
            <span class="font-semibold tabular-nums">{{ $fmt($zeiten['ownPlan']) }} h</span>
            <span class="text-gray-400">{{ __('(aus Schablone „:name")', ['name' => $zeiten['ownTemplateName']]) }}</span>
            @if ($canManagePlanning)
            <button
                type="button"
                @click="loesen()"
                class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >
                {{ __('Lösen') }}
            </button>
            @endif
        </div>

        {{-- Ralf, 2026-09-27: die Aufschlüsselung je Funktionsgruppe auch schon SEHEN,
             solange die Verbindung zur Schablone noch besteht (nicht erst nach "Lösen") -
             rein lesend, gleiche Optik wie die bestehende Schablonen-Info-Anzeige
             (project-template-info.blade.php). --}}
        @if ($project->projectTemplate->functionGroups->isNotEmpty())
            <div class="mt-2 grid grid-cols-2 gap-2 border-t border-gray-200 pt-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($project->projectTemplate->functionGroups->sortBy('name') as $fg)
                    <div class="flex items-center justify-between gap-1 rounded-md border border-gray-200 bg-white px-1.5 py-1 text-xs text-gray-600">
                        <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                        <span class="shrink-0 font-medium text-gray-900">{{ rtrim(rtrim((string) $fg->pivot->planned_hours, '0'), '.') }} h</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@elseif ($zeiten['ownRelevantFunctionGroups']->isNotEmpty() || $zeiten['ownBreakdown']->isNotEmpty())
    @if ($canManagePlanning)
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
                document.getElementById('project-planned-hours-editor').outerHTML = await response.text();
                const planTotal = document.getElementById('project-planned-hours-editor').dataset.planTotal;
                window.dispatchEvent(new CustomEvent('project-planned-hours-changed', { detail: planTotal === '' ? null : Number(planTotal) }));
            },
        }"
        @input="dirty = window.formIsDirty($el)"
        @submit.prevent.stop="save()"
        class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
    >
        {{-- Ralf, 2026-09-27: dieselbe amber/grau-Unterscheidung wie in der "Je Projekt"-
             Tabelle auch hier auf der Kopfzeile selbst - "gelöst" (dieser Block) amber,
             noch verknüpft (Block oben) bleibt neutral grau. --}}
        <div class="flex items-center justify-between">
            <p class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-amber-700" title="{{ __('Eigener Wert - nicht mehr mit der Schablone verbunden') }}">
                <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                {{ __('Planstunden je Funktionsgruppe') }}
            </p>
            <p class="text-xs text-gray-400">
                {{ __('Summe') }}: <span x-text="Object.values(hours).reduce((sum, v) => sum + (parseFloat(v) || 0), 0).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })"></span> h
            </p>
        </div>
        <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($zeiten['ownRelevantFunctionGroups'] as $fg)
                <label class="flex items-center justify-between gap-1 rounded-md border border-gray-200 bg-white px-1.5 py-1 text-xs text-gray-600">
                    <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                    {{-- Ralf, 2026-09-27: "keine Slider mehr vorhanden" - die zuvor hier
                         ausgeblendeten nativen Spinner-Pfeile werden gebraucht. Gleiches
                         Feld-Pattern wie in der Schablonen-Verwaltung
                         (admin/project-templates/partials/content.blade.php): w-20, kein
                         Ausblenden der Spinner. --}}
                    <input
                        type="number"
                        name="hours[{{ $fg->id }}]"
                        x-model="hours['{{ $fg->id }}']"
                        min="0"
                        max="999"
                        step="0.5"
                        placeholder="–"
                        class="w-20 shrink-0 rounded-md border-gray-300 py-0.5 text-xs tabular-nums"
                    >
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
        <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Planstunden je Funktionsgruppe') }}</p>
                <p class="text-xs text-gray-400">{{ __('Summe') }}: {{ $fmt($zeiten['ownBreakdown']->sum(fn ($hours) => (float) $hours)) }} h</p>
            </div>
            <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($zeiten['ownRelevantFunctionGroups'] as $fg)
                    <div class="flex items-center justify-between gap-1 rounded-md border border-gray-200 bg-white px-1.5 py-1 text-xs text-gray-600">
                        <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                        <span class="shrink-0 font-medium text-gray-900">{{ $fmt((float) ($zeiten['ownBreakdown']->get((string) $fg->id) ?? 0)) }} h</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@else
    <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500">
        {{ __('Kein Plan hinterlegt') }}
    </div>
@endif

</div>
