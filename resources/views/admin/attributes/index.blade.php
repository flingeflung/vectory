<x-admin-layout>
    <script>
        window.__attributesDirtyForms = new Set();
    </script>

    @if (session('status') === 'attributes-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div
        x-data="{ activeTab: {{ \Illuminate\Support\Js::from(request('bereich', 'stammdaten')) }} }"
        x-init="window.adminPageIsDirty = () => window.__attributesDirtyForms.size > 0"
        class="flex flex-1 min-h-0 flex-col"
    >
        <div class="mb-3 flex shrink-0 gap-4 border-b border-gray-200 text-sm">
            @foreach (['stammdaten' => __('Stammdaten'), 'ablaufdaten' => __('Ablaufdaten'), 'typspezifisch' => __('Typspezifische Attribute')] as $key => $sectionLabel)
                <button
                    type="button"
                    @click="activeTab = {{ \Illuminate\Support\Js::from($key) }}"
                    :class="activeTab === {{ \Illuminate\Support\Js::from($key) }} ? 'border-b-2 border-gray-800 font-medium text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                    class="pb-2"
                >
                    {{ $sectionLabel }}
                </button>
            @endforeach
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            @foreach (['stammdaten', 'ablaufdaten', 'typspezifisch'] as $section)
                <div x-show="activeTab === {{ \Illuminate\Support\Js::from($section) }}" x-cloak class="space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-4" x-data="{ creating: false, newType: 'text' }">
                        <div class="mb-2 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-semibold text-gray-500">{{ __('Felder') }}</div>
                                <p class="text-xs text-gray-400">{{ __('Feste Felder (Schloss-Symbol) lassen sich nur per Drag & Drop einsortieren, nicht umbenennen/löschen.') }}</p>
                            </div>
                            <button type="button" @click="creating = !creating" class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                + {{ __('Neu') }}
                            </button>
                        </div>

                        <form
                            x-show="creating"
                            x-cloak
                            method="POST"
                            action="{{ route('admin.projektattribute.store') }}"
                            class="mb-3 space-y-2 rounded-md border border-gray-200 p-2"
                        >
                            @csrf
                            <input type="hidden" name="section" value="{{ $section }}">
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500">{{ __('Bezeichnung') }}</label>
                                    <input type="text" name="label" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <div class="w-40">
                                    <label class="block text-xs text-gray-500">{{ __('Feldtyp') }}</label>
                                    <select name="data_type" x-model="newType" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                                        @foreach ($dataTypes as $value => $typeLabel)
                                            <option value="{{ $value }}">{{ $typeLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <label x-show="newType === 'select'" x-cloak class="flex items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" name="multiple" value="1" class="rounded border-gray-300">
                                {{ __('Mehrfachauswahl erlauben') }}
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                    {{ __('Abbrechen') }}
                                </button>
                                <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                    {{ __('Anlegen') }}
                                </button>
                            </div>
                        </form>

                        <div
                            x-data="{
                                async saveOrder() {
                                    const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                                    await fetch({{ \Illuminate\Support\Js::from(route('admin.projektattribute.reorder')) }}, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                                        body: JSON.stringify({ attributes: ids }),
                                    });
                                },
                            }"
                            x-sort="saveOrder()"
                            class="space-y-1"
                        >
                            @forelse ($attributesBySection->get($section, collect()) as $attribute)
                                <div x-sort:item="{{ $attribute->id }}" class="rounded-md border border-gray-200 px-2 py-1" x-data="{ managingOptions: false }">
                                    @if ($attribute->system)
                                        <div class="flex items-center gap-2">
                                            <span x-sort:handle class="shrink-0 cursor-move text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                                            <span class="shrink-0 text-gray-300" title="{{ __('Festes Feld - nur die Reihenfolge ist änderbar') }}">🔒</span>
                                            <span class="flex-1 text-sm text-gray-700">{{ $attribute->label }}</span>
                                        </div>
                                    @else
                                    <div class="flex items-center gap-2">
                                        <span x-sort:handle class="shrink-0 cursor-move text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.projektattribute.update', $attribute) }}"
                                            class="flex flex-1 items-center gap-2"
                                            x-data="{ dirty: false }"
                                            @input="dirty = window.formIsDirty($el, window.__attributesDirtyForms)"
                                            @submit="dirty = false; window.__attributesDirtyForms.delete($el)"
                                        >
                                            @csrf
                                            <input type="text" name="label" value="{{ $attribute->label }}" required class="flex-1 rounded-md border-gray-300 text-sm">
                                            <span class="shrink-0 text-xs text-gray-400">{{ $dataTypes[$attribute->data_type] }}{{ $attribute->multiple ? ' ('.__('Mehrfachauswahl').')' : '' }}</span>
                                            <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                                {{ __('Speichern') }}
                                            </button>
                                        </form>
                                        @if ($attribute->data_type === 'select')
                                            <button type="button" @click="managingOptions = !managingOptions" class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                                {{ __('Optionen') }}
                                            </button>
                                        @endif
                                        <form method="POST" action="{{ route('admin.projektattribute.destroy', $attribute) }}" x-ref="deleteForm" class="hidden">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                        <button
                                            type="button"
                                            @click="window.deleteWithConfirm($refs.deleteForm, {
                                                message: {{ \Illuminate\Support\Js::from(__('Dieses Attribut wirklich endgültig löschen? Vorhandene Werte in Projekten gehen dabei verloren.')) }},
                                            })"
                                            class="shrink-0 rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                                        >
                                            {{ __('Löschen') }}
                                        </button>
                                    </div>
                                    @endif

                                    @if ($attribute->data_type === 'select')
                                        <div x-show="managingOptions" x-cloak class="mt-2 space-y-1.5 border-t border-gray-100 pt-2">
                                            @foreach ($attribute->options as $option)
                                                <div class="flex items-center gap-2" x-data="{}">
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.projektattribute.optionen.update', $option) }}"
                                                        class="flex flex-1 items-center gap-2"
                                                        x-data="{ dirty: false }"
                                                        @input="dirty = window.formIsDirty($el, window.__attributesDirtyForms)"
                                                        @submit="dirty = false; window.__attributesDirtyForms.delete($el)"
                                                    >
                                                        @csrf
                                                        <input type="text" name="label" value="{{ $option->label }}" required class="flex-1 rounded-md border-gray-300 text-xs">
                                                        <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded bg-btn-primary px-1.5 py-0.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                                            {{ __('Speichern') }}
                                                        </button>
                                                    </form>
                                                    <form x-ref="deleteForm" method="POST" action="{{ route('admin.projektattribute.optionen.destroy', $option) }}" class="hidden">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <button
                                                        type="button"
                                                        @click="window.deleteWithConfirm($refs.deleteForm, {
                                                            message: {{ \Illuminate\Support\Js::from(__('Diese Option wirklich löschen?')) }},
                                                        })"
                                                        class="shrink-0 rounded border border-red-300 px-1.5 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                                    >
                                                        {{ __('Löschen') }}
                                                    </button>
                                                </div>
                                            @endforeach
                                            <form method="POST" action="{{ route('admin.projektattribute.optionen.store', $attribute) }}" class="flex items-center gap-2">
                                                @csrf
                                                <input type="text" name="label" placeholder="{{ __('Neue Option') }}" required class="flex-1 rounded-md border-gray-300 text-xs">
                                                <button type="submit" class="shrink-0 rounded bg-btn-primary px-1.5 py-0.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                                    {{ __('Hinzufügen') }}
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-gray-400">{{ __('Noch keine Felder in diesem Bereich.') }}</p>
                            @endforelse
                        </div>
                    </div>

                    @if ($section === 'typspezifisch' && $attributesBySection->get('typspezifisch', collect())->isNotEmpty())
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('Zuordnung zu Projektarten') }}</div>
                            <p class="mb-2 text-xs text-gray-400">{{ __('Klick schaltet die Zuordnung sofort um, kein Speichern-Button nötig.') }}</p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr>
                                            <th class="sticky left-0 bg-white pb-2 pr-3">{{ __('Projektart') }}</th>
                                            @foreach ($attributesBySection->get('typspezifisch') as $attribute)
                                                <th class="whitespace-nowrap px-2 pb-2 text-center font-medium text-gray-600">{{ $attribute->label }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($categories as $category)
                                            @foreach ($category->subs as $sub)
                                                <tr class="border-t border-gray-100">
                                                    <td class="sticky left-0 whitespace-nowrap bg-white py-1.5 pr-3 text-gray-700">{{ $category->name }}: {{ $sub->name }}</td>
                                                    @foreach ($attributesBySection->get('typspezifisch') as $attribute)
                                                        <td class="px-2 py-1.5 text-center">
                                                            <input
                                                                type="checkbox"
                                                                @checked(in_array($sub->id, $assignments->get($attribute->id, []), true))
                                                                @click="
                                                                    fetch({{ \Illuminate\Support\Js::from(route('admin.projektattribute.projektart.toggle', $attribute)) }}, {
                                                                        method: 'POST',
                                                                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                                                                        body: 'project_type_sub_id={{ $sub->id }}',
                                                                    });
                                                                "
                                                            >
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-admin-layout>
