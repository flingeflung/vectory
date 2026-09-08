<x-admin-layout>
    {{--
        Sicherheitsabfrage bei Reiter-/Sidebar-Wechsel (window.navigateOrConfirm,
        siehe layouts/app.blade.php) prüft global window.adminPageIsDirty(). Diese
        Seite kann mehrere unabhängige Formulare haben (Kunden verwalten: je eine
        Zeile pro Kunde) - ein gemeinsames Set sammelt, welche davon gerade
        ungespeichert geändert sind (Ralfs Bug-Report: Kundenname geändert, nicht
        gespeichert, Reiter gewechselt, keine Abfrage).

        Stolperfalle, die den ersten Versuch kaputt gemacht hat: ein normales
        <script> HIER (im Seiteninhalt) läuft VOR dem <script> weiter unten in
        layouts/app.blade.php, das window.adminPageIsDirty auf den Standardwert
        (() => false) setzt - die Reihenfolge im DOM entscheidet bei normalen
        Scripts, nicht die Position im Blade-Code. Der Standardwert hätte also
        die Zuweisung hier sofort wieder überschrieben. x-init läuft dagegen erst,
        wenn Alpine die Seite initialisiert - garantiert NACH allen normalen
        <script>-Tags der Seite, deshalb hier statt in einem <script>.
    --}}
    <script>
        window.__configDirtyForms = new Set();
    </script>
    <div class="max-w-2xl" x-data x-init="window.adminPageIsDirty = () => window.__configDirtyForms.size > 0">
        {{-- Direkter Einstiegspunkt für die vier "klitzekleinen" Verwalten-
             Overlays - bisher nur über "verwalten" neben dem jeweiligen Feld
             im Personen-Overlay erreichbar. Ralf: "direkten Zugangspunkt...
             im Admin-Bereich", aber explizit KEINE eigenen Admin-Tabs dafür
             (hatte er bei den Geschäftsbereichen schon mal als "völlig
             übertrieben" verworfen) - deshalb hier nur Buttons, die
             dieselben globalen Modals öffnen, kein neuer Verwaltungscode. --}}
        <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-semibold text-gray-900">{{ __('Stammdaten verwalten') }}</div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'company-manager' }))"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    {{ \App\Models\SystemSetting::companyLabelPlural() }}
                </button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'department-manager' }))"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    {{ __('Abteilungen') }}
                </button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'business-unit-manager' }))"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    {{ __('Geschäftsbereiche') }}
                </button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'legacy-role-manager' }))"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    {{ __('Rollen') }}
                </button>
                {{-- Platzhalter, noch ohne Funktion (Ralf: "dann haben wir
                     das als Platzhalter") - es gibt für diese drei Kataloge
                     noch keine eigene Verwaltung, siehe Rollout-Pfad-
                     Artifact ("bekannte Lücke"). Bewusst sichtbar deaktiviert
                     statt anklickbar-aber-wirkungslos. --}}
                <button type="button" disabled title="{{ __('Noch nicht verfügbar') }}" class="cursor-not-allowed rounded-md border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-400">
                    {{ __('Workflows') }}
                </button>
            </div>
        </div>

        @if ($settings->isNotEmpty())
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
        @endif

        @unless ($multiTenantEnabled)
            {{-- Ohne Mandantenfähigkeit gibt's nur den einen Mandanten - der
                 Projektpfad wird hier direkt gepflegt, statt in einer
                 "Kunden verwalten"-Liste (die es in diesem Modus nicht gibt). --}}
            <div class="{{ $settings->isNotEmpty() ? 'mt-6 ' : '' }}rounded-lg border border-gray-200 bg-white p-4">
                <div
                    x-data="{ dirty: false, show: false }"
                    x-init="@if (session('status') === 'config-updated') show = true; setTimeout(() => show = false, 2000) @endif"
                >
                    <form method="POST" action="{{ route('admin.kunden.update', $currentTenant) }}" @input="dirty = true; window.__configDirtyForms.add($el)" class="space-y-2">
                        @csrf
                        <input type="hidden" name="name" value="{{ $currentTenant->name }}">
                        <label class="block text-sm font-medium text-gray-700">{{ __('Projektpfad') }}</label>
                        <input
                            type="text"
                            name="project_path"
                            value="{{ old('project_path', $currentTenant->project_path) }}"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="text-xs text-gray-400">{{ __('Basisverzeichnis für Vectory-Projektdateien.') }}</p>

                        <label class="block text-sm font-medium text-gray-700">{{ __('Info-E-Mail') }}</label>
                        <input
                            type="email"
                            name="notification_email"
                            value="{{ old('notification_email', $currentTenant->notification_email) }}"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="text-xs text-gray-400">{{ __('Ziel für von Vectory verschickte Mails, z.B. Projektanfragen.') }}</p>

                        <div class="flex items-center gap-4 pt-2">
                            <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                                {{ __('Speichern') }}
                            </button>
                            <p x-show="show" x-cloak x-transition class="text-sm text-green-600">{{ __('Gespeichert.') }}</p>
                        </div>
                    </form>
                </div>
            </div>
        @endunless
    </div>
</x-admin-layout>
