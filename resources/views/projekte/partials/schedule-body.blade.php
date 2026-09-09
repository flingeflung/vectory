@php
    $hasProposal = $proposal !== null;
@endphp

<div
    x-data="{
        reference: {{ \Illuminate\Support\Js::from($referenceStepId) }},
        async recalculate() {
            if (!this.reference) { return; }
            await window.reloadProjectSchedule({{ \Illuminate\Support\Js::from(route('projekte.termine.recalculate', $project)) }}, { reference_step_id: this.reference });
        },
        async apply(stepId) {
            await window.reloadProjectSchedule({{ \Illuminate\Support\Js::from(route('projekte.termine.apply', $project)) }}, { reference_step_id: this.reference, apply_step_id: stepId });
        },
        async applyAll() {
            await window.reloadProjectSchedule({{ \Illuminate\Support\Js::from(route('projekte.termine.apply', $project)) }}, { reference_step_id: this.reference });
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
                    <th class="pb-2 pr-2">{{ __('Dauer (AT)') }}</th>
                    <th class="pb-2 pr-2">{{ __('Termin') }}</th>
                    @if ($hasProposal)
                        <th class="pb-2 pr-2">{{ __('Neu berechnet') }}</th>
                        <th class="pb-2 pr-2"></th>
                    @endif
                    <th class="w-16 pb-2 pr-2 text-center">{{ __('Berechnung-referenz') }}</th>
                    <th class="pb-2 pr-2 text-center">{{ __('Start') }}</th>
                    <th class="pb-2 text-center">{{ __('Ende') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($steps as $pws)
                    @php $proposedDate = $proposal?->get($pws->id); @endphp
                    <tr class="border-t border-gray-100 {{ $referenceStepId === $pws->id ? 'bg-indigo-50' : '' }}">
                        <td class="py-1.5 pr-2">
                            <input
                                type="text"
                                value="{{ $pws->effectiveMilestoneTitle() }}"
                                placeholder="{{ $pws->workflowStep->title }}"
                                class="w-full rounded border-gray-300 text-xs"
                                @change="
                                    fetch({{ \Illuminate\Support\Js::from(route('projekte.termine.update-field', [$project, $pws])) }}, {
                                        method: 'PATCH',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ milestone_title: $event.target.value }),
                                    });
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
                                    fetch({{ \Illuminate\Support\Js::from(route('projekte.termine.update-field', [$project, $pws])) }}, {
                                        method: 'PATCH',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ duration_days: $event.target.value }),
                                    });
                                "
                            >
                        </td>
                        <td class="py-1.5 pr-2">
                            <input
                                type="date"
                                value="{{ $pws->due_date?->format('Y-m-d') }}"
                                class="rounded border-gray-300 text-xs"
                                @change="
                                    fetch({{ \Illuminate\Support\Js::from(route('projekte.termine.update-field', [$project, $pws])) }}, {
                                        method: 'PATCH',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ due_date: $event.target.value }),
                                    });
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
                                    fetch({{ \Illuminate\Support\Js::from(route('projekte.termine.start-end', [$project, $pws])) }}, {
                                        method: 'PATCH',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ type: 'start' }),
                                    });
                                "
                            >
                        </td>
                        <td class="py-1.5 text-center">
                            <input
                                type="radio"
                                name="is_end"
                                @checked($pws->effectiveIsEnd())
                                @click="
                                    fetch({{ \Illuminate\Support\Js::from(route('projekte.termine.start-end', [$project, $pws])) }}, {
                                        method: 'PATCH',
                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ type: 'end' }),
                                    });
                                "
                            >
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-1 text-xs text-gray-400"><sup>1</sup> {{ __('Termine, die für das Projekt als Start- bzw. Enddatum gelten sollen.') }}</div>

        <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3">
            <button
                type="button"
                :disabled="!reference"
                :class="reference ? 'border-btn-secondary-border bg-btn-secondary text-gray-700 hover:bg-btn-secondary-hover' : 'cursor-not-allowed border-gray-200 text-gray-300'"
                @click="recalculate()"
                class="rounded border px-3 py-1.5 text-xs font-medium"
            >
                {{ __('Neu berechnen') }}
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
