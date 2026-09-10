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
    <label class="block text-xs text-gray-500">{{ $attribute->label }}</label>
    @switch($attribute->data_type)
        @case('textarea')
            <textarea name="attributes[{{ $attribute->key }}]" rows="2" class="mt-0.5 w-full rounded border-gray-300 text-sm">{{ $value }}</textarea>
            @break

        @case('number')
            <input type="number" name="attributes[{{ $attribute->key }}]" value="{{ $value }}" class="mt-0.5 w-full rounded border-gray-300 text-sm">
            @break

        @case('date')
            <input type="date" name="attributes[{{ $attribute->key }}]" value="{{ $value }}" class="mt-0.5 rounded border-gray-300 text-sm">
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
                <select name="attributes[{{ $attribute->key }}][]" multiple size="{{ min(4, max(2, $attribute->options->count())) }}" class="mt-0.5 w-full rounded border-gray-300 text-sm">
                    @foreach ($attribute->options as $option)
                        <option value="{{ $option->value }}" @selected(in_array($option->value, (array) $value, true))>{{ $option->label }}</option>
                    @endforeach
                </select>
            @else
                <select name="attributes[{{ $attribute->key }}]" class="mt-0.5 w-full rounded border-gray-300 text-sm">
                    <option value="">{{ __('– nicht gesetzt –') }}</option>
                    @foreach ($attribute->options as $option)
                        <option value="{{ $option->value }}" @selected($value === $option->value)>{{ $option->label }}</option>
                    @endforeach
                </select>
            @endif
            @break

        @default
            <input type="text" name="attributes[{{ $attribute->key }}]" value="{{ $value }}" class="mt-0.5 w-full rounded border-gray-300 text-sm">
    @endswitch
</div>
