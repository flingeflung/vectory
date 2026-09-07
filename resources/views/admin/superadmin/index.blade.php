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
    </div>
</x-admin-layout>
