<div class="space-y-3" x-data="{ creating: false }">
    <div x-show="!creating">
        <button type="button" @click="creating = true; $nextTick(() => $refs.newName.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
            + {{ __('Mail-Vorlage anlegen') }}
        </button>
    </div>
    <form x-show="creating" x-cloak method="POST" action="{{ route('admin.mail-vorlagen.store') }}" class="space-y-2 rounded-md border border-gray-200 p-2" x-data="{}" @input="window.__mailTemplatesDirtyForms.add($el)" @submit="window.__mailTemplatesDirtyForms.delete($el)">
        @csrf
        <div>
            <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
            <input type="text" name="name" x-ref="newName" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500">{{ __('Text') }}</label>
            <textarea name="body" x-ref="newBody" rows="4" class="mt-0.5 w-full rounded-md border-gray-300 text-sm"></textarea>
            @if ($placeholders->isNotEmpty())
                <div class="mt-1 flex flex-wrap gap-1">
                    <span class="text-xs text-gray-400">{{ __('Feld einfügen:') }}</span>
                    @foreach ($placeholders as $placeholder)
                        <button
                            type="button"
                            @click="window.insertMailPlaceholder($refs.newBody, {{ \Illuminate\Support\Js::from('{'.$placeholder['key'].'}') }})"
                            class="rounded border border-gray-300 bg-btn-secondary px-1.5 py-0.5 text-xs text-gray-700 hover:bg-btn-secondary-hover"
                        >
                            {{ $placeholder['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="flex justify-end gap-2">
            <button type="button" @click="creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Abbrechen') }}
            </button>
            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Anlegen') }}
            </button>
        </div>
    </form>

    <div class="max-h-96 space-y-2 overflow-y-auto">
        @forelse ($templates as $template)
            <div class="rounded-md border border-gray-200 p-2" x-data="{}">
                <form data-row-form x-data="{ dirty: false }" @input="dirty = window.formIsDirty($el, window.__mailTemplatesDirtyForms)" method="POST" action="{{ route('admin.mail-vorlagen.update', $template) }}" class="space-y-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                        <input type="text" name="name" value="{{ $template->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Text') }}</label>
                        <textarea name="body" x-ref="body" rows="4" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">{{ $template->body }}</textarea>
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
                    <div class="flex justify-end">
                        <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-2 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </form>
                <form x-ref="deleteForm" method="POST" action="{{ route('admin.mail-vorlagen.destroy', $template) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="mt-2 flex justify-end">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from(__('Diese Mail-Vorlage wirklich endgültig löschen?')) }},
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>
        @empty
            <div class="p-3 text-sm text-gray-400">{{ __('Noch keine Mail-Vorlagen angelegt.') }}</div>
        @endforelse
    </div>
</div>
