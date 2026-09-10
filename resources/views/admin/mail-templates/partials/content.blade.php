<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Vorlagen, analog zur Workflow-Liste. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.mail-vorlagen')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newTemplate: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Mail-Vorlagen') }}</span>
                <button type="button" @click="newTemplate = !newTemplate; if (newTemplate) $nextTick(() => $refs.newTemplateName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newTemplate" x-cloak method="POST" action="{{ route('admin.mail-vorlagen.store') }}" class="mb-2 flex gap-1.5 rounded border border-gray-200 p-2">
                    <input type="text" name="name" x-ref="newTemplateName" placeholder="{{ __('Name der Vorlage') }}" class="w-full min-w-0 flex-1 rounded-md border-gray-300 text-xs" required>
                    @csrf
                    <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Anlegen') }}
                    </button>
                </form>

                @if ($templates->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Mail-Vorlagen angelegt.') }}</div>
                @else
                    @foreach ($templates as $template)
                        <a
                            :href="navUrl({ template: {{ $template->id }} })"
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

    {{-- Rechts: die gewählte Vorlage zum Bearbeiten, oder ein Hinweis, wenn keine ausgewählt ist. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedTemplate)
            <div class="flex min-h-0 flex-1 flex-col" x-data="{ dirty: false }">
                {{-- WICHTIG: Speichern- und Lösch-Formular dürfen NICHT
                     verschachtelt sein (HTML erlaubt kein <form> im <form> -
                     der Browser reißt sonst das versteckte "_method=DELETE"-
                     Feld ins äußere Formular mit rein, Speichern löscht dann
                     tatsächlich). Deshalb hier zwei GESCHWISTER-Formulare;
                     der Speichern-Button hängt sich per form="…" von außen
                     an sein Formular, "dirty" sitzt darum im gemeinsamen
                     äußeren Scope statt im Formular selbst. --}}
                <form
                    id="mail-template-form-{{ $selectedTemplate->id }}"
                    data-row-form
                    method="POST"
                    action="{{ route('admin.mail-vorlagen.update', $selectedTemplate) }}"
                    class="flex min-h-0 flex-1 flex-col"
                    @input="dirty = window.formIsDirty($el, window.__mailTemplatesDirtyForms)"
                    @submit="dirty = false; window.__mailTemplatesDirtyForms.delete($el)"
                >
                @csrf
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Name der Vorlage') }}</label>
                        <input type="text" name="name" value="{{ $selectedTemplate->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Betreff') }}</label>
                        <input type="text" name="subject" x-ref="subject" value="{{ $selectedTemplate->subject }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        @if ($placeholders->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span class="text-xs text-gray-400">{{ __('Feld einfügen:') }}</span>
                                @foreach ($placeholders as $placeholder)
                                    <button
                                        type="button"
                                        @click="window.insertMailPlaceholder($refs.subject, {{ \Illuminate\Support\Js::from('{'.$placeholder['key'].'}') }})"
                                        class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 text-xs text-gray-700 hover:bg-btn-secondary-hover"
                                    >
                                        {{ $placeholder['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Text') }}</label>
                        <textarea name="body" x-ref="body" rows="10" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">{{ $selectedTemplate->body }}</textarea>
                        @if ($placeholders->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span class="text-xs text-gray-400">{{ __('Feld einfügen:') }}</span>
                                @foreach ($placeholders as $placeholder)
                                    <button
                                        type="button"
                                        @click="window.insertMailPlaceholder($refs.body, {{ \Illuminate\Support\Js::from('{'.$placeholder['key'].'}') }})"
                                        class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 text-xs text-gray-700 hover:bg-btn-secondary-hover"
                                    >
                                        {{ $placeholder['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                </form>

                <form x-ref="deleteForm" method="POST" action="{{ route('admin.mail-vorlagen.destroy', $selectedTemplate) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="shrink-0 flex items-center justify-between border-t border-gray-100 p-3">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Mail-Vorlage wirklich endgültig löschen?')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                    <button type="submit" form="mail-template-form-{{ $selectedTemplate->id }}" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
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
