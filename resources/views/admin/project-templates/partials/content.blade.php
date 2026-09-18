<div x-data="{ creating: false }" class="space-y-2">
    <div x-show="!creating">
        <button type="button" @click="creating = true; $nextTick(() => $refs.newName.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
            + {{ __('Neue Projektschablone') }}
        </button>
    </div>

    <form x-show="creating" x-cloak method="POST" action="{{ route('admin.projektschablonen.store') }}" class="rounded-md border border-gray-200 p-3">
        @csrf
        @include('admin.project-templates.partials.fields', ['template' => null])
        <div class="mt-3 flex justify-end gap-2">
            <button type="button" @click="creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
                {{ __('Abbrechen') }}
            </button>
            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Speichern') }}
            </button>
        </div>
    </form>

    <div class="space-y-2">
        @forelse ($templates as $template)
            <div class="rounded-md border border-gray-200 p-3 {{ ! $template->active ? 'bg-gray-50' : '' }}" x-data="{}">
                @php
                    $createdByName = $template->createdByUser?->person?->fullName() ?? $template->createdByUser?->name ?? '–';
                    $updatedByName = $template->updatedByUser?->person?->fullName() ?? $template->updatedByUser?->name;
                @endphp
                <form data-row-form x-data="{ dirty: false }" @input="dirty = window.formIsDirty($el)" @submit="dirty = false" method="POST" action="{{ route('admin.projektschablonen.update', $template) }}">
                    @csrf
                    @include('admin.project-templates.partials.fields', ['template' => $template])
                    <div class="mt-2 flex items-center justify-between">
                        <p class="text-xs text-gray-400">
                            {{ __('angelegt von :name am :date', ['name' => $createdByName, 'date' => $template->created_at?->format('d.m.Y')]) }}
                            @if ($updatedByName)
                                , {{ __('geändert von :name am :date', ['name' => $updatedByName, 'date' => $template->updated_at?->format('d.m.Y')]) }}
                            @endif
                        </p>
                        <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.projektschablonen.destroy', $template) }}" x-ref="deleteForm" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <div class="mt-1 flex justify-end">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, { message: {{ \Illuminate\Support\Js::from(__('Diese Projektschablone wirklich endgültig löschen?')) }} })"
                        class="rounded-md border border-red-300 px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>
        @empty
            <p class="px-2 py-1 text-xs text-gray-400">{{ __('Noch keine Projektschablonen angelegt.') }}</p>
        @endforelse
    </div>
</div>
