<x-admin-layout>
    <div class="max-w-2xl">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div
                x-data="{ dirty: false, show: false }"
                x-init="@if (session('status') === 'superadmin-updated') show = true; setTimeout(() => show = false, 2000) @endif"
            >
                <form method="POST" action="{{ route('admin.superadmin.update') }}" @input="dirty = true" @change="dirty = true" class="space-y-5">
                    @csrf

                    <div>
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="multi_tenant_enabled" value="1" @checked($multiTenantEnabled) class="mt-0.5 rounded border-gray-300">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">{{ __('Mandantenfähigkeit aktiv') }}</span>
                                <span class="mt-1 block text-xs text-gray-400">
                                    {{ __('Installations-weiter Lizenzmodell-Schalter, gilt für alle Mandanten. Aus: Installation direkt bei einem Kunden, ein einzelner (Standard-)Mandant, keine Umschalter-Oberfläche sichtbar. An: Installation bei einem Dienstleister mit mehreren Kunden - Kundenverwaltung und Mandanten-Umschalter werden verfügbar.') }}
                                </span>
                            </span>
                        </label>
                    </div>

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

        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-900">{{ __('Kunden verwalten') }}</div>
            <p class="mb-3 text-xs text-gray-400">{{ __('Jeder Kunde ist ein eigener, vollständig getrennter Mandant. Fürs Erste nur der Name - weitere Angaben (Ansprechpartner, Projektpfad usw.) kommen bei Bedarf dazu.') }}</p>

            <form method="POST" action="{{ route('admin.kunden.store') }}" class="flex items-end gap-2 rounded-md border border-gray-200 p-2">
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
                    <form
                        method="POST"
                        action="{{ route('admin.kunden.update', $tenant) }}"
                        x-data="{ dirty: false }"
                        @input="dirty = true"
                        class="flex items-end gap-2 rounded-md border border-gray-200 p-2"
                    >
                        @csrf
                        <div class="flex-1">
                            <input type="text" name="name" value="{{ $tenant->name }}" required class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <button type="submit" x-show="dirty" x-cloak class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                            {{ __('Speichern') }}
                        </button>
                    </form>
                @empty
                    <div class="text-sm text-gray-400">{{ __('Noch keine Kunden angelegt.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
