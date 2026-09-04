@php
    $fieldsByKey = collect($filterFields)->keyBy('key');
@endphp

<div
    x-data="{
        active: {{ \Illuminate\Support\Js::from(array_values($activeFilterFields)) }},
        allFields: {{ \Illuminate\Support\Js::from(array_map(fn ($f) => ['key' => $f['key'], 'label' => $f['label']], $filterFields)) }},
        get availableFields() {
            return this.allFields.filter(f => ! this.active.includes(f.key));
        },
        addField(key) {
            if (key && ! this.active.includes(key)) {
                this.active.push(key);
            }
        },
        removeField(key) {
            this.active = this.active.filter(k => k !== key);
        },
    }"
>
    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-medium text-gray-900">{{ __('Projektfilter') }}</h2>
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'projektfilter' }))"
            class="text-gray-400 hover:text-gray-600"
            aria-label="{{ __('Abbrechen') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <form id="projektfilter-form" method="GET" action="{{ route('projekte') }}" class="max-h-[70vh] overflow-y-auto px-6 py-4 space-y-3 text-sm">
        {{-- Immer vorhanden (auch ohne aktive Felder) - sonst kann der Server ein bewusst
             leer abgeschicktes Formular nicht von "nie abgeschickt" unterscheiden und fällt
             fälschlich auf die zuletzt gespeicherte Feldauswahl zurück. --}}
        <input type="hidden" name="projektfilter_submitted" value="1">
        @if ($sort)
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
        @endif

        <div>
            <label class="block text-xs text-gray-500 mb-1">{{ __('Feld hinzufügen') }}</label>
            <select @change="addField($event.target.value); $event.target.value = ''" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">{{ __('– auswählen –') }}</option>
                <template x-for="field in availableFields" :key="field.key">
                    <option :value="field.key" x-text="field.label"></option>
                </template>
            </select>
        </div>

        <div class="space-y-2">
            @foreach ($filterFields as $field)
                <template x-if="active.includes('{{ $field['key'] }}')">
                    <div class="flex items-start gap-2 rounded-md border border-gray-200 bg-gray-50 p-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500 mb-0.5">{{ $field['label'] }}</label>

                            @php
                                $currentValue = old('filter.'.$field['key'], array_key_exists($field['key'], $filters) ? $filters[$field['key']] : null);
                            @endphp
                            @if ($field['type'] === 'select')
                                <select name="filter[{{ $field['key'] }}]" class="w-full rounded border-gray-300 py-1 text-sm">
                                    @unless ($field['no_placeholder'] ?? false)
                                        <option value="">{{ __('Alle') }}</option>
                                    @endunless
                                    @foreach ($field['options'] as $value => $option)
                                        @php
                                            $optionInactive = is_array($option) && ($option['inactive'] ?? false);
                                            $optionLabel = is_array($option) ? $option['label'] : $option;
                                        @endphp
                                        <option
                                            value="{{ $value }}"
                                            @selected($currentValue !== null && (string) $currentValue === (string) $value)
                                            @class(['text-gray-400' => $optionInactive])
                                        >{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field['type'] === 'multiselect')
                                @php
                                    $selected = array_map('strval', (array) old('filter.'.$field['key'], $filters[$field['key']] ?? []));
                                @endphp
                                @php
                                    $layoutClass = match ($field['columns'] ?? null) {
                                        2 => 'grid grid-cols-2 max-h-56 overflow-y-auto',
                                        3 => 'grid grid-cols-3 max-h-56 overflow-y-auto',
                                        default => 'flex flex-wrap',
                                    };
                                @endphp
                                <div class="{{ $layoutClass }} gap-x-5 gap-y-1 rounded border border-gray-300 bg-white p-2">
                                    @foreach ($field['options'] as $option)
                                        <label class="flex items-center gap-1 text-xs text-gray-600">
                                            <input
                                                type="checkbox"
                                                name="filter[{{ $field['key'] }}][]"
                                                value="{{ $option['value'] }}"
                                                class="shrink-0 rounded border-gray-300"
                                                @checked(in_array((string) $option['value'], $selected, true))
                                            >
                                            {{ $option['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($field['type'] === 'grouped_multiselect')
                                @php
                                    $selected = array_map('strval', (array) old('filter.'.$field['key'], $filters[$field['key']] ?? []));
                                @endphp
                                <div class="max-h-48 overflow-y-auto rounded border border-gray-300 bg-white p-2 space-y-2">
                                    @foreach ($field['groups'] as $group)
                                        <div>
                                            <div class="text-xs font-semibold text-gray-700">{{ $group['label'] }}</div>
                                            <div class="mt-0.5 pl-3 space-y-0.5">
                                                @foreach ($group['options'] as $option)
                                                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                                        <input
                                                            type="checkbox"
                                                            name="filter[{{ $field['key'] }}][]"
                                                            value="{{ $option['value'] }}"
                                                            class="rounded border-gray-300"
                                                            @checked(in_array((string) $option['value'], $selected, true))
                                                        >
                                                        {{ $option['label'] }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($field['type'] === 'date_range')
                                <div class="flex items-center gap-2">
                                    <input type="date" name="filter[{{ $field['key'] }}][from]" value="{{ old('filter.'.$field['key'].'.from', $filters[$field['key']]['from'] ?? '') }}" class="w-full rounded border-gray-300 py-1 text-sm">
                                    <span class="text-gray-400">–</span>
                                    <input type="date" name="filter[{{ $field['key'] }}][to]" value="{{ old('filter.'.$field['key'].'.to', $filters[$field['key']]['to'] ?? '') }}" class="w-full rounded border-gray-300 py-1 text-sm">
                                </div>
                            @else
                                <input type="text" name="filter[{{ $field['key'] }}]" value="{{ old('filter.'.$field['key'], $filters[$field['key']] ?? '') }}" class="w-full rounded border-gray-300 py-1 text-sm">
                            @endif
                        </div>

                        <button type="button" @click="removeField('{{ $field['key'] }}')" class="mb-1 text-gray-400 hover:text-red-600" title="{{ __('Feld entfernen') }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <input type="hidden" name="filter_fields[]" value="{{ $field['key'] }}">
                    </div>
                </template>
            @endforeach
        </div>
    </form>

    <div class="flex items-center justify-between border-t border-gray-200 px-6 py-4">
        <button type="button" @click="$dispatch('close-modal', 'projektfilter')" class="text-sm text-gray-600 hover:text-gray-900">
            {{ __('Abbrechen') }}
        </button>
        <div class="flex items-center gap-3">
            <button type="button" @click="active = []" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('Alle Filter zurücksetzen') }}
            </button>
            <button type="submit" form="projektfilter-form" class="rounded bg-gray-800 px-4 py-2 text-xs font-medium text-white hover:bg-gray-700">
                {{ __('Filtern') }}
            </button>
        </div>
    </div>
</div>
