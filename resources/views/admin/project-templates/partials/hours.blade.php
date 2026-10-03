{{--
    Stunden je Funktionsgruppe (Step 2 der Kapa-Planung, Ralf 2026-09-18) - seit 2026-10-03 Teil des
    Haupt-Formulars der Schablone (ein Speichern) und direkt unter den Hauptfeldern, weil sie das
    sind, was ein Projekt tatsächlich von seiner Schablone übernimmt. Welche Funktionsgruppen hier
    stehen, bestimmt der gekoppelte Workflow (ProjectTemplate::relevantFunctionGroups()); eine
    Sammelprojekt-Schablone zeigt den vollen Katalog. Erwartet $template und die Alpine-Variable
    "hours" im umgebenden Formular.
--}}
@php($relevantFunctionGroups = $template->relevantFunctionGroups())
<div class="mt-4 border-t border-gray-100 pt-3">
    @if (! $template->workflow_id && ! $template->unrestricted_function_groups)
        <p class="text-xs text-gray-400">{{ __('Erst einen Workflow koppeln, um Stunden je Funktionsgruppe zu planen (oder als Sammelprojekt-Schablone markieren).') }}</p>
    @elseif ($relevantFunctionGroups->isEmpty())
        <p class="text-xs text-gray-400">{{ __('Der gekoppelte Workflow hat noch keinen Schritten Funktionsgruppen zugeordnet.') }}</p>
    @else
        <input type="hidden" name="hours_present" value="1">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-800">{{ __('Stunden je Funktionsgruppe') }}</p>
            <p class="text-xs text-gray-500">
                {{ __('Summe') }}: <span class="font-medium" x-text="Object.values(hours).reduce((sum, v) => sum + (parseFloat(v) || 0), 0).toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })"></span> h
            </p>
        </div>
        <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($relevantFunctionGroups as $fg)
                <label class="flex items-center justify-between gap-1 rounded-md border border-gray-200 px-1.5 py-1 text-xs text-gray-600">
                    <span class="min-w-0 truncate" title="{{ $fg->name }}">{{ $fg->short_name }}</span>
                    <input type="number" name="hours[{{ $fg->id }}]" x-model="hours['{{ $fg->id }}']" min="0" max="999" step="0.5" placeholder="–" class="w-20 shrink-0 rounded-md border-gray-300 text-xs tabular-nums">
                </label>
            @endforeach
        </div>
    @endif
</div>
