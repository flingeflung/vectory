<x-admin-layout>
    <div class="max-w-2xl">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div
                x-data="{ dirty: false, show: false }"
                x-init="@if (session('status') === 'superadmin-updated') show = true; setTimeout(() => show = false, 2000) @endif"
            >
                <form method="POST" action="{{ route('admin.superadmin.update') }}" @input="dirty = window.formIsDirty($el)" @change="dirty = window.formIsDirty($el)" class="space-y-5">
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
                            class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
                        >
                            {{ __('Speichern') }}
                        </button>
                        <p x-show="show" x-cloak x-transition class="text-sm text-green-600">{{ __('Gespeichert.') }}</p>
                    </div>
                </form>
            </div>
        </div>

        <p class="mt-4 text-xs text-gray-400">
            {{ __('Kundenverwaltung findet der normale Admin in seinem eigenen Konfig-Bereich, sobald Mandantenfähigkeit aktiv ist - der Super-Admin ist nach der Ersteinrichtung hier fertig.') }}
        </p>

        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
            <div class="text-sm font-medium text-gray-700">{{ __('Übersetzung (GUI-Texte)') }}</div>
            <p class="mt-1 text-xs text-gray-400">
                {{ __('Lädt alle im Tool verwendeten Oberflächen-Texte als Datei herunter, bereit für den Übersetzer - dieselbe Datei mit den ausgefüllten Übersetzungen hier anschließend wieder hochladen, um sie zu übernehmen.') }}
            </p>

            @if (session('status') === 'translations-uploaded')
                <x-flash-message class="mt-3 px-3 py-2 text-sm">{{ __('Übersetzung übernommen.') }}</x-flash-message>
            @endif

            <div x-data="{ locale: '{{ array_key_first($translatableLocales) }}' }" class="mt-3 space-y-3">
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Sprache') }}</label>
                    <select x-model="locale" class="mt-0.5 w-40 rounded-md border-gray-300 text-sm">
                        @foreach ($translatableLocales as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <a
                        :href="{{ \Illuminate\Support\Js::from(route('admin.superadmin.uebersetzung.download')) }} + '?locale=' + locale"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >
                        {{ __('Übersetzungsdatei herunterladen') }}
                    </a>

                    <form method="POST" action="{{ route('admin.superadmin.uebersetzung.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="locale" :value="locale">
                        <input type="file" name="translation_file" accept="application/json,.json" required class="text-xs">
                        <button type="submit" class="inline-flex items-center rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
