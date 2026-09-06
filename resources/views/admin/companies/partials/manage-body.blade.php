<div class="space-y-3">
    <form method="POST" action="{{ route('admin.companies.store') }}" class="flex items-end gap-2 rounded-md border border-gray-200 p-2">
        @csrf
        <div class="flex-1">
            <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
        </div>
        <div class="w-24">
            <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
            <input type="text" name="short_name" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
        </div>
        <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
            {{ __('Anlegen') }}
        </button>
    </form>

    <div class="max-h-80 space-y-2 overflow-y-auto">
        @forelse ($companies as $company)
            <div class="rounded-md border border-gray-200 p-2">
                <form data-row-form x-data="{ dirty: false }" @input="dirty = true" method="POST" action="{{ route('admin.companies.update', $company) }}" class="flex items-end gap-2">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500">{{ __('Firma Langname') }}</label>
                        <input type="text" name="name" value="{{ $company->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="w-24">
                        <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                        <input type="text" name="short_name" value="{{ $company->short_name }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" x-show="dirty" x-cloak class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Speichern') }}
                    </button>
                </form>

                <div x-data="{ confirming: false }" class="mt-2">
                    <div x-show="!confirming" class="flex justify-end">
                        <button type="button" @click="confirming = true" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                            {{ __('Löschen') }}
                        </button>
                    </div>
                    <form x-show="confirming" x-cloak method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="space-y-1.5">
                        @csrf
                        @method('DELETE')
                        @if ($company->people_count > 0)
                            <div class="text-xs text-gray-400">
                                {{ trans_choice(
                                    'Wenn die Firma gelöscht wird, ohne die damit verknüpfte Person einer anderen Firma zuzuweisen, wird ihre Firmenzuweisung auf „– nicht zugewiesen –“ geändert.|Wenn die Firma gelöscht wird, ohne die damit verknüpften :count Personen einer anderen Firma zuzuweisen, wird ihre Firmenzuweisung auf „– nicht zugewiesen –“ geändert.',
                                    $company->people_count,
                                    ['count' => $company->people_count]
                                ) }}
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <select name="reassign_to" class="rounded-md border-gray-300 text-xs">
                                    <option value="">{{ __('– nicht zugewiesen –') }}</option>
                                    @foreach ($companies as $target)
                                        @if ($target->id !== $company->id)
                                            <option value="{{ $target->id }}">{{ $target->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button type="button" @click="confirming = false" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                                <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Endgültig löschen') }}</button>
                            </div>
                        @else
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="confirming = false" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                                <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Löschen') }}</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        @empty
            <div class="p-3 text-sm text-gray-400">{{ __('Noch keine Firmen angelegt.') }}</div>
        @endforelse
    </div>
</div>
