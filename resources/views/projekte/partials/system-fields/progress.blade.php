{{--
    Ralf, 2026-09-11: bewusst kein eigener Wert - reines Duplikat der schon
    in Stammdaten/Status berechneten Fortschrittsanzeige (Project::progressPercent()),
    nur zusätzlich hier in Ablaufdaten sichtbar. Nicht editierbar.
--}}
@php
    $hasCurrentWfsStep = $project->projectWorkflowSteps->contains('is_current', true);
    $progress = $hasCurrentWfsStep ? $project->progressPercent() : null;
@endphp
<div>
    <label class="block text-xs text-gray-500">{{ __('Projektfortschritt') }}</label>
    @if ($progress !== null)
        <div class="mt-0.5">
            <div class="mb-0.5 text-xs text-gray-500">{{ $progress }} %</div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
                <div class="h-full rounded-full bg-blue-500" style="width: {{ $progress }}%"></div>
            </div>
        </div>
    @else
        <div class="mt-0.5 text-gray-400">–</div>
    @endif
</div>
