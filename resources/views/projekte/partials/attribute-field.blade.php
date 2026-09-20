{{--
    Rendert EIN Projektattribut passend zu seinem Feldtyp (Admin >
    Projektattribute) - wiederverwendet in allen drei Bereichen
    (Stammdaten/Ablaufdaten/Typspezifisch), damit die Feldtyp-Logik nur an
    einer Stelle gepflegt werden muss. Erwartet $attribute und $project.
--}}
@php
    $value = old('attributes.'.$attribute->key, $project->attributes[$attribute->key] ?? null);
@endphp
<div>
    <label class="block text-xs text-gray-500">{{ $attribute->label }}@if ($attribute->required)<span class="text-red-500" title="{{ __('Pflichtfeld') }}"> *</span>@endif</label>
    @switch($attribute->data_type)
        @case('textarea')
            <textarea
                name="attributes[{{ $attribute->key }}]"
                rows="2"
                @if ($attribute->max_length) maxlength="{{ $attribute->max_length }}" @endif
                class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm"
            >{{ $value }}</textarea>
            @if ($attribute->max_length)
                <p class="mt-0.5 text-xs text-gray-400">{{ __('Max. :max Zeichen', ['max' => $attribute->max_length]) }}</p>
            @endif
            @break

        @case('number')
            @php
                $step = $attribute->number_decimals !== null ? (1 / (10 ** $attribute->number_decimals)) : 'any';
            @endphp
            <div class="mt-0.5 flex items-center gap-1.5">
            <input
                type="number"
                name="attributes[{{ $attribute->key }}]"
                value="{{ $value }}"
                @if ($attribute->number_min !== null) min="{{ $attribute->numberMinDisplay() }}" @endif
                @if ($attribute->number_max !== null) max="{{ $attribute->numberMaxDisplay() }}" @endif
                step="{{ $step }}"
                class="min-w-0 flex-1 rounded border-gray-300 py-1 text-sm"
            >
            @if ($attribute->unit)<span class="shrink-0 text-xs text-gray-500">{{ $attribute->unit }}</span>@endif
            </div>
            @if ($attribute->number_min !== null || $attribute->number_max !== null)
                <p class="mt-0.5 text-xs text-gray-400">
                    @if ($attribute->number_min !== null && $attribute->number_max !== null)
                        {{ __('Bereich :min – :max', ['min' => $attribute->numberMinDisplay(), 'max' => $attribute->numberMaxDisplay()]) }}
                    @elseif ($attribute->number_min !== null)
                        {{ __('Mindestens :min', ['min' => $attribute->numberMinDisplay()]) }}
                    @else
                        {{ __('Höchstens :max', ['max' => $attribute->numberMaxDisplay()]) }}
                    @endif
                </p>
            @endif
            @break

        @case('date')
            <input type="date" name="attributes[{{ $attribute->key }}]" value="{{ $value }}" class="mt-0.5 rounded border-gray-300 py-1 text-sm">
            @break

        @case('boolean')
            <label class="mt-1 flex items-center gap-1.5 text-xs text-gray-700">
                <input type="hidden" name="attributes[{{ $attribute->key }}]" value="0">
                <input type="checkbox" name="attributes[{{ $attribute->key }}]" value="1" class="rounded border-gray-300" @checked($value)>
                {{ __('Ja') }}
            </label>
            @break

        @case('select')
            @if ($attribute->multiple)
                <select name="attributes[{{ $attribute->key }}][]" multiple size="{{ min(4, max(2, $attribute->options->count())) }}" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
                    @foreach ($attribute->options as $option)
                        <option value="{{ $option->value }}" @selected(in_array($option->value, (array) $value, true))>{{ $option->label }}</option>
                    @endforeach
                </select>
            @else
                <select name="attributes[{{ $attribute->key }}]" class="mt-0.5 w-full rounded border-gray-300 py-1 text-sm">
                    <option value="">{{ __('– nicht gesetzt –') }}</option>
                    @foreach ($attribute->options as $option)
                        <option value="{{ $option->value }}" @selected($value === $option->value)>{{ $option->label }}</option>
                    @endforeach
                </select>
            @endif
            @break

        @default
            <div class="mt-0.5 flex items-center gap-1.5">
            <input
                type="text"
                name="attributes[{{ $attribute->key }}]"
                value="{{ $value }}"
                maxlength="{{ $attribute->max_length ?? 255 }}"
                class="min-w-0 flex-1 rounded border-gray-300 py-1 text-sm"
            >
            @if ($attribute->unit)<span class="shrink-0 text-xs text-gray-500">{{ $attribute->unit }}</span>@endif
            </div>
            @if ($attribute->max_length)
                <p class="mt-0.5 text-xs text-gray-400">{{ __('Max. :max Zeichen', ['max' => $attribute->max_length]) }}</p>
            @endif
    @endswitch
</div>
