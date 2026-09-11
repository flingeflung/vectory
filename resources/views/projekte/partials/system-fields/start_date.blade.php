@php
    // Start/Ende werden einseitig aus dem als Start/Ende markierten
    // Workflow-Schritt übernommen (ProjectWorkflowStepObserver), sobald ein
    // solcher Schritt existiert - das Feld hier manuell zu ändern, wäre dann
    // wirkungslos (der nächste Termin am WFS überschreibt es wieder) und
    // damit irreführend. Also nur editierbar, solange es keinen WFS gibt,
    // der diese Rolle trägt (Ralf-Feedback).
    $startStep = $project->projectWorkflowSteps->first(fn ($pws) => $pws->effectiveIsStart());
    $endStep = $project->projectWorkflowSteps->first(fn ($pws) => $pws->effectiveIsEnd());
    $stepLabel = fn ($pws) => $pws->effectiveMilestoneTitle() ?: $pws->workflowStep->title;
@endphp
{{-- Ralf: "Warum nicht 'Start/Ende' ... als je eine Zeile" - beide Felder
     bilden inhaltlich immer ein Paar, deshalb als ein gemeinsames Feld statt
     zweier einzeln sortierbarer Felder (verhindert, dass sie durch
     Umsortieren auseinanderfallen). --}}
<div class="flex gap-4">
    <div>
        <label class="block text-xs text-gray-500">{{ __('Start') }}</label>
        <input
            type="date"
            name="start_date"
            value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}"
            @disabled($startStep)
            class="mt-0.5 rounded border-gray-300 py-1 text-sm disabled:bg-gray-50 disabled:text-gray-400"
        >
        @if ($startStep)
            <div class="mt-0.5 text-xs text-gray-400">{{ __('Aus Workflow-Schritt „:step“ übernommen.', ['step' => $stepLabel($startStep)]) }}</div>
        @endif
    </div>
    <div>
        <label class="block text-xs text-gray-500">{{ __('Ende') }}</label>
        <input
            type="date"
            name="end_date"
            value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}"
            @disabled($endStep)
            class="mt-0.5 rounded border-gray-300 py-1 text-sm disabled:bg-gray-50 disabled:text-gray-400"
        >
        @if ($endStep)
            <div class="mt-0.5 text-xs text-gray-400">{{ __('Aus Workflow-Schritt „:step“ übernommen.', ['step' => $stepLabel($endStep)]) }}</div>
        @endif
    </div>
</div>
