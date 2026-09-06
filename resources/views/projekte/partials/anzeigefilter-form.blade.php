@php
    $activeSet = $sets->firstWhere('is_active', true);
@endphp

<div
    x-data="{
        items: {{ \Illuminate\Support\Js::from(collect($allColumns)->map(fn ($c) => [
            'key' => $c['key'],
            'label' => $c['label'],
            'visible' => $c['visible'],
            'longTextCapable' => $c['long_text'],
            'longText' => $c['show_long_text'],
            'shortLength' => $c['short_length'],
        ])->values()) }},
        markAll(value) {
            this.items.forEach(item => item.visible = value);
        },
    }"
>
    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
        <h2 class="text-lg font-medium text-gray-900">{{ __('Anzeigefilter') }}</h2>
        <button
            type="button"
            @click="$dispatch('close-modal', 'anzeigefilter')"
            class="text-gray-400 hover:text-gray-600"
            aria-label="{{ __('Abbrechen') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="max-h-[70vh] overflow-y-auto px-6 py-4 space-y-4">
        @if ($errors->any())
            <div class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Meine Filtersets') }}</label>
            <div class="flex gap-2">
                <select
                    class="flex-1 rounded-md border-gray-300 text-sm"
                    onchange="document.getElementById('activate-form-' + this.value).submit()"
                >
                    @foreach ($sets as $set)
                        <option value="{{ $set->id }}" @selected($set->is_active)>{{ $set->name }}</option>
                    @endforeach
                </select>

                @if ($sets->count() > 1)
                    <form
                        method="POST"
                        action="{{ route('projekte.anzeigefilter.sets.destroy', $activeSet) }}"
                        x-data="{ async confirmAndSubmit(e) { if (await window.confirmDialog({ title: '{{ __('Filterset löschen') }}', message: '{{ __('Dieses Filterset wirklich löschen?') }}', confirmLabel: '{{ __('Löschen') }}' })) { e.target.submit(); } } }"
                        @submit.prevent="confirmAndSubmit($event)"
                    >
                        @csrf
                        @method('delete')
                        <button type="submit" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm text-gray-500 hover:bg-gray-50" title="{{ __('Aktuelles Set löschen') }}">
                            🗑
                        </button>
                    </form>
                @endif
            </div>
            @foreach ($sets as $set)
                <form id="activate-form-{{ $set->id }}" method="POST" action="{{ route('projekte.anzeigefilter.sets.activate', $set) }}" class="hidden">
                    @csrf
                </form>
            @endforeach

            <div class="mt-2 flex gap-2">
                <input
                    type="text"
                    form="anzeigefilter-form"
                    name="name"
                    placeholder="{{ __('Neuer Name für „Speichern unter“') }}"
                    class="flex-1 rounded-md border-gray-300 text-sm"
                >
            </div>
        </div>

        <div class="flex gap-2">
            <button type="button" @click="markAll(true)" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('Alle markieren') }}</button>
            <button type="button" @click="markAll(false)" class="text-sm text-indigo-600 hover:text-indigo-800">{{ __('Keinen markieren') }}</button>
        </div>

        <form method="POST" action="{{ route('projekte.anzeigefilter.update') }}" id="anzeigefilter-form">
            @csrf
            <input type="hidden" name="set_id" value="{{ $activeSet->id ?? '' }}">

            <div class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                <input type="checkbox" checked disabled class="rounded border-gray-300">
                <span class="flex-1 font-medium">{{ __('PN') }}</span>
                <span class="text-xs">{{ __('immer sichtbar, nicht verschiebbar') }}</span>
            </div>

            <ul x-sort class="mt-2 space-y-1">
                <template x-for="item in items" :key="item.key">
                    <li
                        x-sort:item="item.key"
                        class="flex items-center gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm cursor-move"
                    >
                        <span x-sort:handle class="text-gray-300">⠿</span>
                        <input type="checkbox" x-model="item.visible" class="rounded border-gray-300 text-indigo-600">
                        <span class="flex-1" x-text="item.label"></span>
                        <template x-if="item.longTextCapable">
                            <label class="flex items-center gap-1 text-xs text-gray-500">
                                <input type="checkbox" x-model="item.longText" class="rounded border-gray-300 text-indigo-600">
                                {{ __('Text lang') }}
                            </label>
                        </template>
                        <template x-if="item.longTextCapable">
                            <label class="flex items-center gap-1 text-xs text-gray-500">
                                {{ __('Kurztext-Länge') }}
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    x-model.number="item.shortLength"
                                    :name="'short_length[' + item.key + ']'"
                                    class="w-16 rounded-md border-gray-300 text-xs"
                                >
                            </label>
                        </template>

                        <template x-if="item.visible">
                            <input type="hidden" name="visible[]" :value="item.key">
                        </template>
                        <template x-if="item.longText">
                            <input type="hidden" name="long_text[]" :value="item.key">
                        </template>
                        <input type="hidden" name="order[]" :value="item.key">
                    </li>
                </template>
            </ul>
        </form>
    </div>

    <div class="flex items-center justify-between border-t border-gray-200 px-6 py-4">
        <button type="button" @click="$dispatch('close-modal', 'anzeigefilter')" class="text-sm text-gray-600 hover:text-gray-900">
            {{ __('Abbrechen') }}
        </button>
        <div class="flex gap-2">
            <button
                type="submit"
                form="anzeigefilter-form"
                formaction="{{ route('projekte.anzeigefilter.sets.store') }}"
                class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                {{ __('Speichern unter') }}
            </button>
            <button type="submit" form="anzeigefilter-form" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                {{ __('Speichern') }}
            </button>
        </div>
    </div>
</div>
