<x-admin-layout>
    @if (session('status') === 'business-unit-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif

    <div class="max-w-md">
        <div class="mb-1 text-xs font-semibold text-gray-500">{{ __('Geschäftsbereiche') }}</div>
        <div class="mb-3 text-xs text-gray-400">
            {{ __('Reines Zuordnungs-Hilfsattribut einer Person, unabhängig von Firma und Abteilung.') }}
        </div>

        <form method="POST" action="{{ route('admin.geschaeftsbereiche.store') }}" class="mb-4 flex gap-2">
            @csrf
            <input
                type="text"
                name="name"
                placeholder="{{ __('Neuer Geschäftsbereich') }}"
                required
                class="flex-1 rounded-md border-gray-300 text-sm"
            >
            <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                {{ __('Anlegen') }}
            </button>
        </form>

        <div class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            @forelse ($businessUnits as $unit)
                <form method="POST" action="{{ route('admin.geschaeftsbereiche.update', $unit) }}" class="flex items-center gap-2 p-2">
                    @csrf
                    <input
                        type="text"
                        name="name"
                        value="{{ $unit->name }}"
                        class="flex-1 rounded-md border-gray-300 text-sm {{ ! $unit->active ? 'text-gray-400' : '' }}"
                    >
                    <label class="flex items-center gap-1 text-xs text-gray-600">
                        <input type="checkbox" name="active" value="1" @checked($unit->active) class="rounded border-gray-300">
                        {{ __('Aktiv') }}
                    </label>
                    <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Speichern') }}
                    </button>
                </form>
            @empty
                <div class="p-3 text-sm text-gray-400">{{ __('Noch keine Geschäftsbereiche angelegt.') }}</div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
