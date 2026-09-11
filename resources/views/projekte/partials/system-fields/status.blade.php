@php
    $hasCurrentWfsStep = $project->projectWorkflowSteps->contains('is_current', true);
    $creationTypeOptions = [1 => __('Neuerstellung'), 2 => __('Änderung')];
@endphp
{{-- Ralf: "Warum nicht ... 'Projektstatus/Erstellungsstatus' ... als je eine
     Zeile" - beide Felder bilden inhaltlich immer ein Paar, deshalb als ein
     gemeinsames Feld statt zweier einzeln sortierbarer Felder (verhindert,
     dass sie durch Umsortieren auseinanderfallen). --}}
<div class="flex gap-4">
    <div>
        <label class="block text-xs text-gray-500">{{ __('Status') }}</label>
        @if ($hasCurrentWfsStep)
            {{-- Wird automatisch aus dem aktuellen WFS-Schritt abgeleitet (siehe
                 ProjectWorkflowStepController::activate) - kein manuelles Feld mehr,
                 solange ein Schritt aktuell ist. --}}
            <input type="hidden" name="status" value="{{ $project->status }}">
            <div class="mt-0.5 w-full rounded border border-gray-200 bg-gray-50 py-1 px-2 text-sm text-gray-700" title="{{ __('Wird automatisch aus dem aktuellen Workflow-Schritt abgeleitet') }}">
                {{ $statusOptions[$project->status] ?? '–' }}
            </div>
        @else
            <select name="status" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
                @foreach ($statusOptions as $value => $label)
                    @if (! in_array($value, [2, 3], true) || auth()->user()->can('project.complete') || $project->status === $value)
                        <option value="{{ $value }}" @selected(old('status', $project->status) == $value)>{{ $label }}</option>
                    @endif
                @endforeach
            </select>
        @endif
    </div>
    <div>
        <label class="block text-xs text-gray-500">{{ __('Erstellungsstatus') }}</label>
        <select name="creation_type" class="mt-0.5 w-full max-w-[200px] rounded border-gray-300 py-1 text-sm">
            <option value="" @selected(old('creation_type', $project->creation_type) === null)>{{ __('– nicht gesetzt –') }}</option>
            @foreach ($creationTypeOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('creation_type', $project->creation_type) == $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($hasCurrentWfsStep && ($progress = $project->progressPercent()) !== null)
    <div class="mt-1.5">
        <div class="mb-0.5 flex items-center justify-between text-xs text-gray-500">
            <span>{{ __('Fortschritt') }}</span>
            <span>{{ $progress }} %</span>
        </div>
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-blue-500" style="width: {{ $progress }}%"></div>
        </div>
    </div>
@endif
