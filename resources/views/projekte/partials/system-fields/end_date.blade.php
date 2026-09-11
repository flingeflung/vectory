@php
    $endStep = $project->projectWorkflowSteps->first(fn ($pws) => $pws->effectiveIsEnd());
    $stepLabel = fn ($pws) => $pws->effectiveMilestoneTitle() ?: $pws->workflowStep->title;
@endphp
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
