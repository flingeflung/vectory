@php
    $hasProposal = $proposal !== null;
@endphp

<div
    x-data="{
        reference: {{ \Illuminate\Support\Js::from($referenceStepId) }},
        pendingSaves: [],
        busy: false,
        saveField(url, payload) {
            const request = fetch(url, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            }).then(async (response) => {
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || {{ \Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte erneut versuchen.')) }});
                }
                // Rückmeldung wie überall beim Sofort-Speichern: das kleine grüne Gespeichert-Fenster (Ralf, 2026-10-06)
                window.showToast({{ \Illuminate\Support\Js::from(__('Gespeichert.')) }});
                return true;
            }).catch(async (error) => {
                await window.notifyDialog(error.message);
                return false;
            });
            this.pendingSaves.push(request);
            request.finally(() => {
                this.pendingSaves = this.pendingSaves.filter((pending) => pending !== request);
            });
            return request;
        },
        async finishPendingSaves() {
            const results = await Promise.all([...this.pendingSaves]);
            return results.every(Boolean);
        },
        async recalculate() {
            if (!this.reference || this.busy) { return; }
            this.busy = true;
            if (await this.finishPendingSaves()) {
                await window.reloadProjectSchedule({{ \Illuminate\Support\Js::from(route('projekte.termine.recalculate', $project)) }}, { reference_step_id: this.reference });
            }
            this.busy = false;
        },
        async apply(stepId) {
            if (await window.reloadProjectSchedule({{ \Illuminate\Support\Js::from(route('projekte.termine.apply', $project)) }}, { reference_step_id: this.reference, apply_step_id: stepId })) {
                window.showToast({{ \Illuminate\Support\Js::from(__('Gespeichert.')) }});
            }
        },
        async applyAll() {
            const saved = await window.reloadProjectSchedule({{ \Illuminate\Support\Js::from(route('projekte.termine.apply', $project)) }}, { reference_step_id: this.reference });
            if (saved) {
                window.showToast({{ \Illuminate\Support\Js::from(__('Gespeichert.')) }});
            }
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-schedule' }));
        },
    }"
>
    @if ($steps->isEmpty())
        <div class="text-gray-400">{{ __('Keine Schritte mit Termin in diesem Workflow.') }}</div>
    @else
        <table class="w-full text-left text-xs">
            <thead class="text-gray-500">
                <tr>
                    <th class="pb-2 pr-2">{{ __('Workflow-Schritt') }}</th>
                    <th class="pb-2 pr-2">{{ __('Termin-Name') }}</th>
                    <th class="pb-2 pr-2">{{ __('Dauer (AT)') }}</th>
                    <th class="pb-2 pr-2">{{ __('Termin') }}</th>
                    @if ($hasProposal)
                        <th class="pb-2 pr-2">{{ __('Neu berechnet') }}</th>
                        <th class="pb-2 pr-2"></th>
                    @endif
                    <th class="w-16 pb-2 pr-2 text-center">{{ __('Berechnung-referenz') }}</th>
                    <th class="pb-2 pr-2 text-center">{{ __('Start') }}<sup>1</sup></th>
                    <th class="pb-2 text-center">{{ __('Ende') }}<sup>1</sup></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($steps as $pws)
                    @php $proposedDate = $proposal?->get($pws->id); @endphp
                    <tr class="border-t border-gray-100 {{ $referenceStepId === $pws->id ? 'bg-indigo-50' : '' }}">
                        <td class="py-1.5 pr-2 text-gray-700">
                            @if ($pws->workflowStep->is_active)
                                {{ $pws->workflowStep->title }}
                            @else
                                <span class="text-gray-400">{{ __('nur Termin, kein WFS!') }}</span>
                            @endif
                        </td>
                        <td class="py-1.5 pr-2">
                            <input
                                type="text"
                                value="{{ $pws->effectiveMilestoneTitle() }}"
                                placeholder="{{ __('– kein Termin-Name –') }}"
                                class="w-full rounded border-gray-300 text-xs"
                                @change="
                                    saveField({{ \Illuminate\Support\Js::from(route('projekte.termine.update-field', [$project, $pws])) }}, { milestone_title: $event.target.value });
                                "
                            >
                        </td>
                        <td class="py-1.5 pr-2">
                            <input
                                type="number"
                                min="1"
                                value="{{ $pws->effectiveDurationDays() }}"
                                class="w-16 rounded border-gray-300 text-xs"
                                @change="
                                    saveField({{ \Illuminate\Support\Js::from(route('projekte.termine.update-field', [$project, $pws])) }}, { duration_days: $event.target.value });
                                "
                            >
                        </td>
                        <td class="py-1.5 pr-2">
                            <input
                                type="date"
                                value="{{ $pws->due_date?->format('Y-m-d') }}"
                                class="rounded border-gray-300 text-xs"
                                @change="
                                    saveField({{ \Illuminate\Support\Js::from(route('projekte.termine.update-field', [$project, $pws])) }}, { due_date: $event.target.value });
                                "
                            >
                        </td>
                        @if ($hasProposal)
                            <td class="py-1.5 pr-2 font-medium text-indigo-700">
                                {{ $proposedDate?->format('d.m.Y') }}
                            </td>
                            <td class="py-1.5 pr-2">
                                @unless ($referenceStepId === $pws->id)
                                    <button type="button" @click="apply({{ $pws->id }})" class="rounded border border-btn-secondary-border bg-btn-secondary px-1.5 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                        {{ __('übernehmen') }}
                                    </button>
                                @endunless
                            </td>
                        @endif
                        <td class="py-1.5 pr-2 text-center">
                            <input type="radio" name="reference" x-model="reference" :value="{{ $pws->id }}">
                        </td>
                        <td class="py-1.5 pr-2 text-center">
                            <input
                                type="radio"
                                name="is_start"
                                @checked($pws->effectiveIsStart())
                                @click="
                                    saveField({{ \Illuminate\Support\Js::from(route('projekte.termine.start-end', [$project, $pws])) }}, { type: 'start' });
                                "
                            >
                        </td>
                        <td class="py-1.5 text-center">
                            <input
                                type="radio"
                                name="is_end"
                                @checked($pws->effectiveIsEnd())
                                @click="
                                    saveField({{ \Illuminate\Support\Js::from(route('projekte.termine.start-end', [$project, $pws])) }}, { type: 'end' });
                                "
                            >
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-1 text-xs text-gray-400"><sup>1</sup> {{ __('Termine, die für das Projekt als Start- bzw. Enddatum gelten sollen.') }}</div>
        <div class="text-xs text-gray-400">{{ __('Die Neuberechnung zählt nur Werktage (Mo-Fr) und lässt die Feiertage der Organisation aus.') }}</div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3">
            <button
                type="button"
                :disabled="!reference || busy"
                :class="reference && !busy ? 'border-btn-secondary-border bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' : 'cursor-not-allowed border-gray-200 text-gray-300'"
                @click="recalculate()"
                class="rounded border px-3 py-1.5 text-xs font-medium"
            >
                <span x-show="!busy">{{ __('Neu berechnen') }}</span>
                <span x-show="busy" x-cloak class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    {{ __('Bitte warten…') }}
                </span>
            </button>
            <p x-show="!reference" class="text-xs text-gray-400">{{ __('Referenz-Schritt (mit gültigem Termin und Dauer) markieren, um die Berechnung zu starten.') }}</p>

            @if ($hasProposal)
                <button type="button" @click="applyAll()" class="rounded bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                    {{ __('Alle übernehmen und schließen') }}
                </button>
            @endif
        </div>
    @endif
</div>
