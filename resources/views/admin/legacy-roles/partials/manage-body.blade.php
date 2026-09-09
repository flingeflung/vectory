<div class="space-y-3" x-data="{ creating: false }">
    <div x-show="!creating">
        <button type="button" @click="creating = true; $nextTick(() => $refs.newName.focus())" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
            + {{ __('Rolle anlegen') }}
        </button>
    </div>
    <form x-show="creating" x-cloak method="POST" action="{{ route('admin.legacy-roles.store') }}" class="flex items-end gap-2 rounded-md border border-gray-200 p-2">
        @csrf
        <div class="flex-1">
            <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
            <input type="text" name="name" x-ref="newName" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
        </div>
        <button type="button" @click="creating = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">
            {{ __('Abbrechen') }}
        </button>
        <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
            {{ __('Anlegen') }}
        </button>
    </form>

    <div class="max-h-80 space-y-2 overflow-y-auto">
        @forelse ($legacyRoles as $legacyRole)
            <div class="rounded-md border border-gray-200 p-2" x-data="{}">
                <form data-row-form x-data="{ dirty: false }" @input="dirty = true" method="POST" action="{{ route('admin.legacy-roles.update', $legacyRole) }}" class="flex items-end gap-2">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                        <input type="text" name="name" value="{{ $legacyRole->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-2 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.legacy-roles.destroy', $legacyRole) }}" x-ref="deleteForm" class="hidden">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="reassign_to" value="">
                </form>
                <div class="mt-2 flex justify-end">
                    <button
                        type="button"
                        @click="window.deleteWithConfirm($refs.deleteForm, {
                            message: {{ \Illuminate\Support\Js::from($legacyRole->people_count > 0 ? trans_choice('Wird bereits von :count Person verwendet.|Wird bereits von :count Personen verwendet.', $legacyRole->people_count, ['count' => $legacyRole->people_count]).' '.__('Ohne Umhängen wird die Rollenzuweisung auf „– nicht zugewiesen –“ gesetzt.') : __('Diese Rolle wirklich endgültig löschen?')) }},
                            reassignOptions: @js($legacyRole->people_count > 0 ? $legacyRoles->where('id', '!=', $legacyRole->id)->map(fn ($target) => ['value' => (string) $target->id, 'label' => $target->name])->values() : []),
                        })"
                        class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                    >
                        {{ __('Löschen') }}
                    </button>
                </div>
            </div>
        @empty
            <div class="p-3 text-sm text-gray-400">{{ __('Noch keine Rollen angelegt.') }}</div>
        @endforelse
    </div>
</div>
