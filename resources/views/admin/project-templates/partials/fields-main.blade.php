{{--
    Hauptfelder (Name, Workflow, Dauer, Optionen) für Anlegen- und Zeilen-Formular (siehe
    content.blade.php) - $template ist null beim Anlegen. Merkmale: fields-characteristics.
--}}
<div class="flex flex-wrap items-end gap-2">
    <div class="min-w-[200px] flex-1">
        <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
        <input
            type="text"
            name="name"
            value="{{ $template->name ?? '' }}"
            required
            @if (! $template) x-ref="newName" @endif
            class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm"
        >
    </div>
    <div>
        <span class="flex items-center gap-1">
            <label class="block text-xs text-gray-500">{{ __('Workflow') }}</label>
            <span class="shrink-0 text-gray-400" title="{{ __('Bestimmt die für dieses Aufwandsprofil verfügbaren Funktionsgruppen: Sie ergeben sich aus den Schritten des gewählten Workflows. Ohne Workflow sind keine Funktionsgruppen zuweisbar. Ausnahme: Bei einer Sammelprojekt-Aufwandsprofil (siehe Häkchen rechts) ist dieses Feld rein informativ.') }}">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
        </span>
        <select name="workflow_id" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
            <option value="">{{ __('– keiner –') }}</option>
            @foreach ($workflows as $workflow)
                <option value="{{ $workflow->id }}" @class(['text-gray-400' => ! $workflow->active]) @selected(($template->workflow_id ?? null) == $workflow->id)>
                    {{ $workflow->name }}{{ ! $workflow->active ? ' [i]' : '' }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500">{{ __('Dauer') }}</label>
        <div class="flex items-center gap-1">
            <input type="number" name="duration_value" value="{{ $template->duration_value ?? 1 }}" min="0.5" max="999" step="0.5" required class="mt-0.5 w-16 rounded-md border-gray-300 py-1 text-sm">
            <select name="duration_unit" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
                @foreach (\App\Models\ProjectTemplate::durationUnitOptions() as $value => $label)
                    <option value="{{ $value }}" @selected(($template->duration_unit ?? 'weeks') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    {{-- Ralf, 2026-09-28: "brich die beiden Checkboxen immer um, unter die Dropdowns" -
         w-full in der flex-wrap-Zeile erzwingt den Umbruch unabhängig von der
         verfügbaren Breite, statt nur bei zu schmalem Fenster. --}}
    <div class="flex w-full items-center gap-4">
        <span class="flex shrink-0 items-center gap-1">
            <label class="flex items-center gap-1 text-xs text-gray-600">
                <input type="checkbox" name="unrestricted_function_groups" value="1" @checked($template->unrestricted_function_groups ?? false) class="rounded border-gray-300">
                {{ __('Sammelprojekt') }}
            </label>
            <span class="text-gray-400" title="{{ __('Für Aufwandsprofile, die ein Hauptprojekt mit Unterprojekten unterschiedlicher Workflows abdecken: Funktionsgruppen sind dann frei aus dem ganzen Katalog wählbar, unabhängig von einer Workflow-Kopplung.') }}">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
        </span>
        @if ($template)
            <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                <input type="checkbox" name="active" value="1" @checked($template->active) class="rounded border-gray-300">
                {{ __('Aktiv') }}
            </label>
        @endif
    </div>
</div>
