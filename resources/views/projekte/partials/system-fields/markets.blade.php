<div
    x-data="{
        editingMarkets: false,
        marketSets: {{ \Illuminate\Support\Js::from($marketSets->mapWithKeys(fn ($set) => [$set->id => $set->markets->pluck('id')])) }},
        selectedSet: '',
        applySet() {
            if (! this.selectedSet) return;
            const checkboxes = [...this.$root.querySelectorAll('input[name=\'markets[]\']')];

            if (this.selectedSet === '__all__') {
                checkboxes.forEach(cb => cb.checked = true);
            } else if (this.selectedSet === '__none__') {
                checkboxes.forEach(cb => cb.checked = false);
            } else {
                const ids = this.marketSets[this.selectedSet] ?? [];
                checkboxes.forEach(cb => cb.checked = ids.includes(Number(cb.value)));
            }

            this.selectedSet = '';
        },
    }"
>
    <div class="flex items-center gap-2">
        <label class="text-xs text-gray-500">{{ __('Markt') }}</label>
        <button type="button" @click="editingMarkets = !editingMarkets" class="{{ $secondaryBtn }}">
            <span x-show="!editingMarkets">{{ __('Ändern') }}</span>
            <span x-show="editingMarkets" x-cloak>{{ __('Fertig') }}</span>
        </button>
    </div>

    <div x-show="!editingMarkets">
        @if ($project->markets->isEmpty())
            <div class="mt-0.5 text-gray-400">&ndash; {{ __('Kein Markt zugewiesen') }} &ndash;</div>
        @else
            <div class="mt-0.5 text-gray-700">
                @if ($project->markets->count() > 4)
                    <span class="text-xs text-gray-500">{{ $project->markets->count() }} {{ __('Märkte') }}:</span>
                @endif
                @foreach ($project->markets as $market)
                    <span class="font-semibold">{{ $market->country_iso }}{{ strtolower($market->language_code) }}</span>@if ($market->no_translation)*@endif {{ $market->country_short_name }}@if (! $loop->last), @endif
                @endforeach
            </div>
            @if ($project->markets->contains('no_translation', true))
                <div class="mt-0.5 text-xs text-gray-400">* {{ __('Es wird keine Übersetzung für diesen Markt durchgeführt') }}</div>
            @endif
        @endif
    </div>

    <div x-show="editingMarkets" x-cloak class="mt-0.5">
        <div class="mb-1.5 flex items-center gap-1.5">
            <select x-model="selectedSet" class="rounded border-gray-300 py-1 text-xs">
                <option value="">{{ __('Standard-Märkte zuweisen') }}</option>
                <option value="__all__">{{ __('Alle auswählen') }}</option>
                <option value="__none__">{{ __('Alle entfernen') }}</option>
                @if ($marketSets->isNotEmpty())
                    <optgroup label="{{ __('Gespeicherte Sets') }}">
                        @foreach ($marketSets as $set)
                            <option value="{{ $set->id }}">{{ $set->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <button type="button" x-show="selectedSet" x-cloak @click="applySet()" class="rounded bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Zuweisen') }}
            </button>
        </div>

        <div class="grid grid-cols-2 gap-x-4 gap-y-1 max-h-56 overflow-y-auto rounded border border-gray-300 bg-white p-2 text-xs">
        @foreach ($allMarkets as $market)
            <label class="flex items-center gap-1 text-gray-600">
                <input
                    type="checkbox"
                    name="markets[]"
                    value="{{ $market->id }}"
                    class="shrink-0 rounded border-gray-300"
                    @checked($project->markets->contains('id', $market->id))
                >
                {{ $market->label() }}
            </label>
        @endforeach
        </div>
    </div>
</div>
