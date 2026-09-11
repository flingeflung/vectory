@php
    $sectionLabels = ['stammdaten' => __('Stammdaten'), 'ablaufdaten' => __('Ablaufdaten'), 'typspezifisch' => __('Typspezifische Attribute')];
@endphp

<div class="mb-4 shrink-0 rounded-lg border border-gray-200 bg-white p-3" x-data="{ dirty: false }">
    <form
        method="POST"
        action="{{ route('admin.projektkopie-vorlagen.max-kopien.update') }}"
        class="flex items-end gap-2"
        @input="dirty = window.formIsDirty($el, window.__copyTemplatesDirtyForms)"
        @submit="dirty = false; window.__copyTemplatesDirtyForms.delete($el)"
    >
        @csrf
        <div>
            <label class="block text-xs text-gray-500">{{ __('Projekte kopieren: max. Anzahl Kopien') }}</label>
            <input type="number" name="max_project_copies" min="1" max="50" value="{{ $tenant->max_project_copies }}" required class="mt-0.5 w-24 rounded-md border-gray-300 py-1 text-sm">
        </div>
        <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Speichern') }}
        </button>
    </form>
</div>

<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Vorlagen, analog zu den Mail-Vorlagen. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.projektkopie-vorlagen')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newTemplate: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Vorlagen') }}</span>
                <button type="button" @click="newTemplate = !newTemplate; if (newTemplate) $nextTick(() => $refs.newTemplateName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newTemplate" x-cloak method="POST" action="{{ route('admin.projektkopie-vorlagen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newTemplateName" placeholder="{{ __('Name der Vorlage') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Anlegen') }}
                    </button>
                </form>

                @if ($templates->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Vorlagen angelegt.') }}</div>
                @else
                    @foreach ($templates as $template)
                        <a
                            :href="navUrl({ vorlage: {{ $template->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedTemplate?->id === $template->id) data-selected @endif
                            class="flex flex-col rounded px-2 py-1 {{ $selectedTemplate?->id === $template->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            {{ $template->name }}
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: gewählte Vorlage - Name, Feld-Auswahl je Bereich. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedTemplate)
            <div class="flex min-h-0 flex-1 flex-col">
                {{-- Name wird wie die Feld-Haken automatisch gespeichert
                     (Ralf: sonst unlogisch, wenn die Haken sofort wirken,
                     aber der Name einen extra Speichern-Klick braucht) -
                     normaler Formular-Submit statt Fetch, damit die Liste
                     links den neuen Namen sofort mit anzeigt. --}}
                <form
                    method="POST"
                    action="{{ route('admin.projektkopie-vorlagen.update', $selectedTemplate) }}"
                    class="shrink-0 border-b border-gray-100 p-3"
                >
                    @csrf
                    <label class="block text-xs text-gray-500">{{ __('Name der Vorlage') }}</label>
                    <input type="text" name="name" value="{{ $selectedTemplate->name }}" required onchange="this.form.submit()" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                </form>

                <div class="min-h-0 flex-1 overflow-y-auto p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs text-gray-400">{{ __('Markierte Attribute werden beim Kopieren von Projekten vom Quell- in das Zielprojekt übernommen. Änderungen in der Vorlage werden automatisch gespeichert.') }}</p>
                        <div class="flex shrink-0 gap-3">
                            <form method="POST" action="{{ route('admin.projektkopie-vorlagen.alle-markieren', $selectedTemplate) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">{{ __('Alle markieren') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.projektkopie-vorlagen.keinen-markieren', $selectedTemplate) }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">{{ __('Keinen markieren') }}</button>
                            </form>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @foreach ($sectionLabels as $section => $sectionLabel)
                            @if ($attributesBySection->get($section, collect())->isNotEmpty())
                                <div>
                                    <div class="mb-1.5 text-xs font-semibold text-gray-500">{{ $sectionLabel }}</div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                        @foreach ($attributesBySection->get($section) as $attribute)
                                            <label class="flex items-center gap-1.5 text-gray-700">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300"
                                                    @checked(in_array($attribute->id, $selectedFieldIds, true))
                                                    @click="
                                                        fetch({{ \Illuminate\Support\Js::from(route('admin.projektkopie-vorlagen.feld.toggle', $selectedTemplate)) }}, {
                                                            method: 'POST',
                                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                                                            body: 'attribute_id={{ $attribute->id }}',
                                                        });
                                                    "
                                                >
                                                {{ $attribute->label }}
                                                @if ($attribute->system)
                                                    <span class="text-gray-300" title="{{ __('Festes Feld') }}">🔒</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <form x-ref="deleteForm" method="POST" action="{{ route('admin.projektkopie-vorlagen.destroy', $selectedTemplate) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="shrink-0 flex items-center justify-end border-t border-gray-100 p-3">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Vorlage wirklich endgültig löschen?')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wähle links eine Vorlage aus, um sie zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>
