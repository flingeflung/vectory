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
                <form method="POST" action="{{ route('admin.companies.update', $company) }}" class="flex items-end gap-2">
                    @csrf
                    <div class="flex-1">
                        <input type="text" name="name" value="{{ $company->name }}" required class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="w-24">
                        <input type="text" name="short_name" value="{{ $company->short_name }}" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Speichern') }}
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('admin.companies.destroy', $company) }}"
                    class="mt-2 flex items-center justify-end gap-2"
                    onsubmit="return confirm('{{ __('Firma wirklich löschen?') }}')"
                >
                    @csrf
                    @method('DELETE')
                    @if ($company->people_count > 0)
                        <span class="mr-auto text-xs text-gray-400">{{ trans_choice(':count Person|:count Personen', $company->people_count, ['count' => $company->people_count]) }}</span>
                        <select name="reassign_to" class="rounded-md border-gray-300 text-xs">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($companies as $target)
                                @if ($target->id !== $company->id)
                                    <option value="{{ $target->id }}">{{ $target->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                        {{ __('Löschen') }}
                    </button>
                </form>
            </div>
        @empty
            <div class="p-3 text-sm text-gray-400">{{ __('Noch keine Firmen angelegt.') }}</div>
        @endforelse
    </div>
</div>
