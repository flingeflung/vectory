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
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ \App\Models\SystemSetting::companyLabelPlural() }}
                </button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'department-manager' }))"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Abteilungen') }}
                </button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'business-unit-manager' }))"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Geschäftsbereiche') }}
                </button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'legacy-role-manager' }))"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Rollen') }}
                </button>
            </div>
        </div>

        @unless ($multiTenantEnabled)
            {{-- Ohne Mandantenfähigkeit gibt's nur den einen Mandanten - der
                 Projektpfad wird hier direkt gepflegt, statt in einer
                 "Kunden verwalten"-Liste (die es in diesem Modus nicht gibt). --}}
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div
                    x-data="{ dirty: false, show: false }"
                    x-init="@if (session('status') === 'tenant-updated') show = true; setTimeout(() => show = false, 2000) @endif"
                >
                    <form method="POST" action="{{ route('admin.kunden.update', $currentTenant) }}" @input="dirty = window.formIsDirty($el, window.__configDirtyForms)" class="space-y-2">
                        @csrf
                        <input type="hidden" name="name" value="{{ $currentTenant->name }}">
                        <label class="block text-sm font-medium text-gray-700">{{ __('Projektpfad (gesperrt)') }}</label>
                        <input
                            type="text"
                            name="project_path"
                            value="{{ old('project_path', $currentTenant->project_path) }}"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="text-xs text-gray-400">{{ __('Basisverzeichnis für Vectory-Projektdateien. Nur über Vectory erreichbar.') }}</p>

                        <label class="block text-sm font-medium text-gray-700">{{ __('Arbeitsverzeichnis-Pfad') }}</label>
                        <input
                            type="text"
                            name="arbeitsverzeichnis_path"
                            value="{{ old('arbeitsverzeichnis_path', $currentTenant->arbeitsverzeichnis_path) }}"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="text-xs text-gray-400">{{ __('Lokaler Netzwerkordner mit derselben Unterordner-Struktur, z. B. für externe Korrektur-Uploads.') }}</p>

                        <label class="block text-sm font-medium text-gray-700">{{ __('Info-E-Mail') }} <span class="text-red-500">*</span></label>
                        <input
                            type="email"
                            name="notification_email"
                            value="{{ old('notification_email', $currentTenant->notification_email) }}"
                            required
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="text-xs text-gray-400">{{ __('Pflichtfeld. Empfänger für automatische Mitteilungen an diesen Kunden, z. B. Projektanfragen und Rückmeldungen zu Freigaben. Wenn noch keine Sammel-Adresse existiert, tragen Sie zunächst irgendeine gültige Adresse ein - sie lässt sich jederzeit ändern.') }}</p>

                        <div class="flex flex-wrap gap-4">
                            <label class="block text-sm font-medium text-gray-700">{{ __('Maximale Anzahl Projekte im Gantt') }}
                                <input type="number" name="gantt_max_projects" value="{{ old('gantt_max_projects', $currentTenant->gantt_max_projects) }}" min="1" max="200" step="1" required class="mt-1 block w-28 rounded-md border-gray-300 text-sm">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Zeitraster') }}
                                <select name="jobload_time_grid" required class="mt-1 block rounded-md border-gray-300 text-sm">
                                    <option value="60" @selected(old('jobload_time_grid', $currentTenant->jobload_time_grid) == 60)>{{ __('Volle Stunden') }}</option>
                                    <option value="30" @selected(old('jobload_time_grid', $currentTenant->jobload_time_grid) == 30)>{{ __('Halbe Stunden') }}</option>
                                    <option value="15" @selected(old('jobload_time_grid', $currentTenant->jobload_time_grid) == 15)>{{ __('Viertelstunden') }}</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-gray-700" title="{{ __('Wird beim Anlegen eines Logins automatisch als Wochenstunden der Person eingetragen.') }}">{{ __('Standard-Wochenstunden') }}
                                <input type="number" name="default_weekly_hours" value="{{ old('default_weekly_hours', rtrim(rtrim(number_format((float) $currentTenant->default_weekly_hours, 1, '.', ''), '0'), '.')) }}" min="0" max="80" step="0.5" required class="mt-1 block w-28 rounded-md border-gray-300 text-sm">
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('gantt_max_projects')" />
                        <x-input-error :messages="$errors->get('jobload_time_grid')" />
                        <x-input-error :messages="$errors->get('default_weekly_hours')" />

                        <div class="flex items-center gap-4 pt-2">
                            <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
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
