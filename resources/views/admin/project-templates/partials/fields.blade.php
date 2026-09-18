{{--
    Gemeinsame Feldliste für Anlegen- und Zeilen-Formular (siehe
    content.blade.php) - $template ist null beim Anlegen.
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
        <label class="block text-xs text-gray-500">{{ __('Format') }}</label>
        <select name="format" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
            @foreach (\App\Models\ProjectTemplate::formatOptions() as $value => $label)
                <option value="{{ $value }}" @selected(($template->format ?? null) == $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500">{{ __('Workflow') }}</label>
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
    @if ($template)
        <label class="flex shrink-0 items-center gap-1 pb-1.5 text-xs text-gray-600">
            <input type="checkbox" name="active" value="1" @checked($template->active) class="rounded border-gray-300">
            {{ __('Aktiv') }}
        </label>
    @endif
</div>

<div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    {{--
        Farbige Umrandung/Hintergrund je gewähltem Wert (grün = günstig für
        die Dauer, rot = ungünstig) - Viettos gakat.php zeigte das rein
        lesend über die Badge-Farben (get_anteilXX()), hier zusätzlich live
        am bearbeitbaren Pulldown selbst, damit man ein Schablonen-Profil
        auch im (hier editierbaren) Zustand auf einen Blick erfassen kann.
    --}}
    @foreach (\App\Models\ProjectTemplate::characteristicFields() as $field => $meta)
        <div
            x-data="{
                value: {{ \Illuminate\Support\Js::from((string) ($template->$field ?? '')) }},
                colors: {{ \Illuminate\Support\Js::from(collect($meta['options'])->mapWithKeys(fn ($option, $value) => [(string) $value => $option['color']])) }},
            }"
        >
            <label class="block text-xs text-gray-500">{{ $meta['label'] }}</label>
            <select
                name="{{ $field }}"
                required
                x-model="value"
                :class="{
                    'border-green-300 bg-green-50': colors[value] === 'green',
                    'border-lime-300 bg-lime-50': colors[value] === 'lime',
                    'border-amber-300 bg-amber-50': colors[value] === 'amber',
                    'border-red-300 bg-red-50': colors[value] === 'red',
                    'border-gray-300 bg-gray-50': colors[value] === 'gray',
                    'border-gray-300': !colors[value],
                }"
                class="mt-0.5 w-full rounded-md py-1 text-sm"
            >
                <option value="" disabled>{{ __('– auswählen –') }}</option>
                @foreach ($meta['options'] as $value => $option)
                    <option value="{{ $value }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
    @endforeach
</div>

<div class="mt-3">
    <label class="block text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
    <textarea name="remarks" rows="2" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">{{ $template->remarks ?? '' }}</textarea>
</div>
