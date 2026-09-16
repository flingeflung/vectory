<div x-data="{ creating: false }" class="space-y-1.5">
    <form
        x-show="creating"
        x-cloak
        method="POST"
        action="{{ route('admin.papierformate.store') }}"
        class="mb-1.5 flex items-end gap-2 rounded-md border border-gray-200 p-2"
    >
        @csrf
        <div class="flex-1">
            <label class="block text-xs text-gray-500">{{ __('Bezeichnung') }}</label>
            <input type="text" name="name" x-ref="newFormatName" placeholder="{{ __('z. B. DIN A6 hoch') }}" required class="mt-0.5 w-full rounded-md border-gray-300 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500">{{ __('Breite (mm)') }}</label>
            <input type="number" name="width_mm" min="1" step="1" required class="mt-0.5 w-24 rounded-md border-gray-300 py-1 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500">{{ __('Höhe (mm)') }}</label>
            <input type="number" name="height_mm" min="1" step="1" required class="mt-0.5 w-24 rounded-md border-gray-300 py-1 text-sm">
        </div>
        <button type="button" @click="creating = false" class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Speichern') }}
        </button>
    </form>
    <div x-show="!creating">
        <button type="button" @click="creating = true; $nextTick(() => $refs.newFormatName.focus())" class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
            + {{ __('Neu') }}
        </button>
    </div>

    @if ($formats->isEmpty())
        <p class="px-2 py-1 text-xs text-gray-400">{{ __('Noch keine Papierformate angelegt.') }}</p>
    @else
        <div
            x-data="{
                async saveOrder() {
                    const ids = [...this.$el.querySelectorAll('[x-sort\\:item]')].map(el => el.getAttribute('x-sort:item'));
                    await fetch({{ \Illuminate\Support\Js::from(route('admin.papierformate.reorder')) }}, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                        body: JSON.stringify({ formats: ids }),
                    });
                },
            }"
            x-sort="saveOrder()"
            class="space-y-1.5"
        >
            @foreach ($formats as $format)
                <div x-sort:item="{{ $format->id }}" x-data class="rounded-md border border-gray-200 px-2 py-1.5 {{ ! $format->active ? 'bg-gray-50' : '' }}">
                    <form
                        method="POST"
                        action="{{ route('admin.papierformate.update', $format) }}"
                        data-row-form
                        x-data="{ dirty: false }"
                        @input="dirty = window.formIsDirty($el)"
                        @submit="dirty = false"
                    >
                        @csrf
                        <div class="flex items-center gap-2">
                            <span x-sort:handle class="shrink-0 cursor-move text-gray-300 hover:text-gray-500" title="{{ __('Verschieben') }}">⠿</span>
                            <input type="text" name="name" value="{{ $format->name }}" required @class(['min-w-0 flex-1 rounded-md border-gray-300 py-1 text-sm', 'text-gray-400' => ! $format->active])>
                            <input type="number" name="width_mm" value="{{ $format->width_mm }}" min="1" step="1" required class="w-16 rounded-md border-gray-300 py-1 text-xs" title="{{ __('Breite (mm)') }}">
                            <span class="shrink-0 text-xs text-gray-400">x</span>
                            <input type="number" name="height_mm" value="{{ $format->height_mm }}" min="1" step="1" required class="w-16 rounded-md border-gray-300 py-1 text-xs" title="{{ __('Höhe (mm)') }}">
                            <span class="shrink-0 text-xs text-gray-400">mm</span>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                                <input type="checkbox" name="active" value="1" @checked($format->active) class="rounded border-gray-300">
                                {{ __('Aktiv') }}
                            </label>
                            <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                {{ __('Speichern') }}
                            </button>
                        </div>
                        <div class="mt-1 flex items-center gap-2 pl-5 text-xs text-gray-500">
                            <label class="flex items-center gap-1">
                                {{ __('Kurzbezeichnung') }}
                                <input type="text" name="short_name" value="{{ $format->short_name }}" placeholder="{{ __('z. B. A6h') }}" class="w-20 rounded border-gray-300 py-0.5 text-xs">
                            </label>
                            <label class="flex items-center gap-1">
                                <input type="checkbox" name="show_dimensions" value="1" @checked($format->show_dimensions) class="rounded border-gray-300">
                                {{ __('Maße mit anzeigen') }}
                            </label>
                            <label class="flex min-w-0 flex-1 items-center gap-1">
                                {{ __('Bemerkung') }}
                                <input type="text" name="remark" value="{{ $format->remark }}" class="min-w-0 flex-1 rounded border-gray-300 py-0.5 text-xs">
                            </label>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.papierformate.destroy', $format) }}" x-ref="deleteForm{{ $format->id }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                    <div class="mt-1 flex justify-end">
                        <button
                            type="button"
                            @click="window.deleteWithConfirm($refs['deleteForm{{ $format->id }}'], { message: {{ \Illuminate\Support\Js::from(__('Dieses Papierformat wirklich endgültig löschen?')) }} })"
                            class="shrink-0 rounded-md border border-red-300 px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                        >
                            {{ __('Löschen') }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
