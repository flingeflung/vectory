<x-admin-layout>
    {{--
        Sicherheitsabfrage bei Reiter-/Sidebar-Wechsel (window.navigateOrConfirm,
        siehe layouts/app.blade.php) prüft global window.adminPageIsDirty(). Diese
        Seite hat MEHRERE unabhängige Formulare (Haupt-Einstellungen + je eine
        Zeile pro Kunde) - ein gemeinsames Set sammelt, welche davon gerade
        ungespeichert geändert sind, statt nur das Haupt-Formular zu prüfen
        (Ralfs Bug-Report: Kundenname geändert, nicht gespeichert, Reiter
        gewechselt, keine Abfrage).
    --}}
    <script>
        window.__configDirtyForms = new Set();
        window.adminPageIsDirty = () => window.__configDirtyForms.size > 0;
    </script>
    <div class="max-w-2xl">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div
                x-data="{ dirty: false, show: false }"
                x-init="@if (session('status') === 'config-updated') show = true; setTimeout(() => show = false, 2000) @endif"
            >
                <form method="POST" action="{{ route('admin.config.update') }}" @input="dirty = true; window.__configDirtyForms.add($el)" class="space-y-5">
                    @csrf

                    @foreach ($settings as $setting)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ $setting['label'] }}</label>
                            <input
                                type="text"
                                name="values[{{ $setting['key'] }}]"
                                value="{{ old('values.'.$setting['key'], $setting['value']) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <p class="mt-1 text-xs text-gray-400">{{ $setting['description'] }}</p>
                            <x-input-error :messages="$errors->get('values.'.$setting['key'])" class="mt-1" />
                        </div>
                    @endforeach

                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            x-show="dirty"
                            x-cloak
                            class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                        >
                            {{ __('Speichern') }}
                        </button>
                        <p x-show="show" x-cloak x-transition class="text-sm text-green-600">{{ __('Gespeichert.') }}</p>
                    </div>
                </form>
            </div>
        </div>

        @if ($multiTenantEnabled)
            <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
                <div class="mb-3 text-sm font-semibold text-gray-900">{{ __('Kunden verwalten') }}</div>
                <p class="mb-3 text-xs text-gray-400">{{ __('Jeder Kunde ist ein eigener, vollständig getrennter Mandant. Fürs Erste nur der Name - weitere Angaben (Ansprechpartner, Projektpfad usw.) kommen bei Bedarf dazu.') }}</p>

                <form method="POST" action="{{ route('admin.kunden.store') }}" @input="window.__configDirtyForms.add($el)" class="flex items-end gap-2 rounded-md border border-gray-200 p-2">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                        <input type="text" name="name" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                        {{ __('Anlegen') }}
                    </button>
                </form>

                <div class="mt-3 space-y-2">
                    @forelse ($tenants as $tenant)
                        <div class="rounded-md border border-gray-200 p-2">
                            <form
                                method="POST"
                                action="{{ route('admin.kunden.update', $tenant) }}"
                                x-data="{ dirty: false }"
                                @input="dirty = true; window.__configDirtyForms.add($el)"
                                class="flex items-end gap-2"
                            >
                                @csrf
                                <div class="flex-1">
                                    <input type="text" name="name" value="{{ $tenant->name }}" required class="w-full rounded-md border-gray-300 text-sm">
                                </div>
                                <button type="submit" x-show="dirty" x-cloak class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                    {{ __('Speichern') }}
                                </button>
                            </form>

                            @unless ($tenant->hasData())
                                <div x-data="{ confirming: false }" class="mt-2">
                                    <div x-show="!confirming" class="flex justify-end">
                                        <button type="button" @click="confirming = true" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                                            {{ __('Löschen') }}
                                        </button>
                                    </div>
                                    <form x-show="confirming" x-cloak method="POST" action="{{ route('admin.kunden.destroy', $tenant) }}" class="flex items-center justify-end gap-2">
                                        @csrf
                                        @method('DELETE')
                                        <span class="text-xs text-gray-400">{{ __('Dieser Kunde hat noch keine Daten und kann gefahrlos gelöscht werden.') }}</span>
                                        <button type="button" @click="confirming = false" class="rounded-md border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                                        <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Endgültig löschen') }}</button>
                                    </form>
                                </div>
                            @endunless
                        </div>
                    @empty
                        <div class="text-sm text-gray-400">{{ __('Noch keine Kunden angelegt.') }}</div>
                    @endforelse
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
