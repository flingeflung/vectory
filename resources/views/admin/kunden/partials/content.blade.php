<div class="flex flex-1 min-h-0 gap-4">
    {{-- Links: die Kunden, analog zur Workflow-/Mail-Vorlagen-Liste. --}}
    <div
        x-data="{
            navUrl(params) {
                const url = new URL({{ \Illuminate\Support\Js::from(route('admin.kunden')) }}, window.location.origin);
                Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
                return url.pathname + url.search;
            },
        }"
        class="flex w-80 shrink-0 flex-col"
    >
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ newTenant: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-2">
                <span class="text-xs font-semibold text-gray-500">{{ __('Organisationen') }}</span>
                <button type="button" @click="newTenant = !newTenant; if (newTenant) $nextTick(() => $refs.newTenantName.focus())" class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    + {{ __('Neu') }}
                </button>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm" x-init="$nextTick(() => $el.querySelector('[data-selected]')?.scrollIntoView({ block: 'nearest' }))">
                <form x-show="newTenant" x-cloak method="POST" action="{{ route('admin.kunden.store') }}" enctype="multipart/form-data" x-data="{ logoPreview: null, logoName: '' }" class="mb-2 space-y-2 rounded border border-gray-200 p-2" @input="window.__tenantsDirtyForms.add($el)" @submit="window.__tenantsDirtyForms.delete($el)">
                    @csrf
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-2">
                        <div class="mb-2 flex h-16 items-center justify-center rounded bg-white">
                            <img x-show="logoPreview" x-cloak :src="logoPreview" alt="" class="max-h-14 max-w-full object-contain">
                            <span x-show="! logoPreview" class="text-xs text-gray-400">{{ __('Kein Logo ausgewählt') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                {{ __('Logo auswählen') }}
                                <input type="file" name="company_icon" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" class="sr-only" @click="if ($el.form.dataset.dirtyBaseline === undefined) $el.form.dataset.dirtyBaseline = window.formSnapshot($el.form)" @change="const file = $event.target.files[0]; logoName = file?.name || ''; logoPreview = file ? URL.createObjectURL(file) : null">
                            </label>
                            <span class="min-w-0 truncate text-xs text-gray-400" x-text="logoName"></span>
                        </div>
                        <x-input-error :messages="$errors->get('company_icon')" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                        <input type="text" name="name" x-ref="newTenantName" required class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                        <input type="text" name="short_name" maxlength="10" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Projektpfad (gesperrt)') }}</label>
                        <input type="text" name="project_path" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Ordner, unter dem die Projektverzeichnisse dieses Kunden angelegt werden. Nur über Vectory erreichbar.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Arbeitsverzeichnis-Pfad') }}</label>
                        <input type="text" name="arbeitsverzeichnis_path" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Lokaler Netzwerkordner mit derselben Unterordner-Struktur, z. B. für externe Korrektur-Uploads.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Info-E-Mail') }} <span class="text-red-500">*</span></label>
                        <input type="email" name="notification_email" required class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Pflichtfeld. Empfänger für automatische Mitteilungen an diesen Kunden, z. B. Projektanfragen und Rückmeldungen zu Freigaben. Wenn noch keine Sammel-Adresse existiert, tragen Sie zunächst irgendeine gültige Adresse ein - sie lässt sich jederzeit ändern.') }}</p>
                    </div>
                    @if ($tenants->isNotEmpty())
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Als Kopie von') }}</label>
                            <select name="source_tenant_id" class="mt-0.5 w-full rounded-md border-gray-300 text-xs">
                                <option value="">{{ __('– Keine Vorlage –') }}</option>
                                @foreach ($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">{{ __('Übernimmt Funktionsgruppen, Abteilungen, Geschäftsbereiche, Rollen, Workflows, Projektarten und Märkte des gewählten Kunden - keine Personen, Projekte oder Dienstleister.') }}</p>
                        </div>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="newTenant = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                            {{ __('Abbrechen') }}
                        </button>
                        <button type="submit" class="rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </form>

                @if ($tenants->isEmpty())
                    <div class="px-2 py-1 text-gray-400">{{ __('Noch keine Organisationen angelegt.') }}</div>
                @else
                    @foreach ($tenants as $tenant)
                        <a
                            :href="navUrl({ tenant: {{ $tenant->id }} })"
                            onclick="return window.navigateOrConfirm(event)"
                            @if ($selectedTenant?->id === $tenant->id) data-selected @endif
                            class="flex flex-col rounded px-2 py-1 {{ $selectedTenant?->id === $tenant->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                        >
                            <span @class(['text-gray-400' => ! $tenant->is_active])>{{ $tenant->name }}{{ ! $tenant->is_active ? ' [i]' : '' }}</span>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Rechts: der gewählte Kunde zum Bearbeiten, oder ein Hinweis, wenn keiner ausgewählt ist. --}}
    <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
        @if ($selectedTenant)
            <div class="flex min-h-0 flex-1 flex-col" x-data="{ dirty: false, logoPreview: {{ \Illuminate\Support\Js::from($selectedTenant->iconUrl()) }}, logoName: {{ \Illuminate\Support\Js::from($selectedTenant->icon_filename ?? '') }} }">
                {{-- Speichern- und Lösch-Formular als Geschwister, nicht
                     verschachtelt (siehe Mail-Vorlagen: ein <form> im
                     <form> zieht das versteckte "_method=DELETE"-Feld ins
                     äußere Formular, Speichern löscht dann tatsächlich). --}}
                <form
                    id="tenant-form-{{ $selectedTenant->id }}"
                    method="POST"
                    action="{{ route('admin.kunden.update', $selectedTenant) }}"
                    enctype="multipart/form-data"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3"
                    @input="dirty = window.formIsDirty($el, window.__tenantsDirtyForms)"
                    @submit="dirty = false; window.__tenantsDirtyForms.delete($el)"
                >
                    @csrf
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-3">
                        <div class="mb-2 flex h-20 items-center justify-center rounded bg-white">
                            <img x-show="logoPreview" x-cloak :src="logoPreview" alt="" class="max-h-16 max-w-full object-contain">
                            <span x-show="! logoPreview" class="text-xs text-gray-400">{{ __('Kein Logo ausgewählt') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="cursor-pointer rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                                {{ __('Logo auswählen') }}
                                <input type="file" name="company_icon" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" class="sr-only" @click="if ($el.form.dataset.dirtyBaseline === undefined) $el.form.dataset.dirtyBaseline = window.formSnapshot($el.form)" @change="const file = $event.target.files[0]; logoName = file?.name || ''; logoPreview = file ? URL.createObjectURL(file) : logoPreview; dirty = window.formIsDirty($el.form, window.__tenantsDirtyForms)">
                            </label>
                            <span class="min-w-0 truncate text-xs text-gray-400" x-text="logoName"></span>
                        </div>
                        <x-input-error :messages="$errors->get('company_icon')" />
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500">{{ __('Name') }}</label>
                            <input type="text" name="name" value="{{ $selectedTenant->name }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                            <input type="text" name="short_name" value="{{ $selectedTenant->short_name }}" maxlength="10" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Projektpfad (gesperrt)') }}</label>
                        <input type="text" name="project_path" value="{{ $selectedTenant->project_path }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Ordner, unter dem die Projektverzeichnisse dieses Kunden angelegt werden. Nur über Vectory erreichbar.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Arbeitsverzeichnis-Pfad') }}</label>
                        <input type="text" name="arbeitsverzeichnis_path" value="{{ $selectedTenant->arbeitsverzeichnis_path }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Lokaler Netzwerkordner mit derselben Unterordner-Struktur, z. B. für externe Korrektur-Uploads.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Info-E-Mail') }} <span class="text-red-500">*</span></label>
                        <input type="email" name="notification_email" value="{{ $selectedTenant->notification_email }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Pflichtfeld. Empfänger für automatische Mitteilungen an diesen Kunden, z. B. Projektanfragen und Rückmeldungen zu Freigaben. Wenn noch keine Sammel-Adresse existiert, tragen Sie zunächst irgendeine gültige Adresse ein - sie lässt sich jederzeit ändern.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <label class="block text-xs text-gray-500">{{ __('Maximale Anzahl Projekte im Gantt') }}
                            <input type="number" name="gantt_max_projects" value="{{ old('gantt_max_projects', $selectedTenant->gantt_max_projects) }}" min="1" max="200" step="1" required class="mt-0.5 block w-28 rounded-md border-gray-300 text-sm text-gray-800">
                        </label>
                        <label class="block text-xs text-gray-500">{{ __('Zeitraster') }}
                            <select name="jobload_time_grid" required class="mt-0.5 block rounded-md border-gray-300 text-sm text-gray-800">
                                <option value="60" @selected(old('jobload_time_grid', $selectedTenant->jobload_time_grid) == 60)>{{ __('Volle Stunden') }}</option>
                                <option value="30" @selected(old('jobload_time_grid', $selectedTenant->jobload_time_grid) == 30)>{{ __('Halbe Stunden') }}</option>
                                <option value="15" @selected(old('jobload_time_grid', $selectedTenant->jobload_time_grid) == 15)>{{ __('Viertelstunden') }}</option>
                            </select>
                        </label>
                        <label class="block text-xs text-gray-500" title="{{ __('Wird beim Anlegen eines Logins automatisch als Wochenstunden der Person eingetragen.') }}">{{ __('Standard-Wochenstunden') }}
                            <input type="number" name="default_weekly_hours" value="{{ old('default_weekly_hours', rtrim(rtrim(number_format((float) $selectedTenant->default_weekly_hours, 1, '.', ''), '0'), '.')) }}" min="0" max="80" step="0.5" required class="mt-0.5 block w-28 rounded-md border-gray-300 text-sm text-gray-800">
                        </label>
                        <label class="block text-xs text-gray-500" title="{{ __('Wird beim Anlegen eines Logins automatisch als Urlaubstage der Person eingetragen.') }}">{{ __('Standard-Urlaubstage') }}
                            <input type="number" name="default_vacation_days" value="{{ old('default_vacation_days', rtrim(rtrim(number_format((float) $selectedTenant->default_vacation_days, 1, '.', ''), '0'), '.')) }}" min="0" max="100" step="0.5" required class="mt-0.5 block w-28 rounded-md border-gray-300 text-sm text-gray-800">
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('gantt_max_projects')" />
                    <x-input-error :messages="$errors->get('jobload_time_grid')" />
                    <x-input-error :messages="$errors->get('default_weekly_hours')" />
                    <x-input-error :messages="$errors->get('default_vacation_days')" />
                    <div class="rounded-md border border-gray-200 bg-gray-50 p-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_home_tenant" value="1" @checked($selectedTenant->is_home_tenant) class="rounded border-gray-300">
                            {{ __('Heimat-Mandant') }}
                        </label>
                        <p class="mt-0.5 text-xs text-gray-400">{{ __('Admins dieses Mandanten sehen und bearbeiten alle Mandanten. Admins jedes anderen Mandanten bleiben auf ihren eigenen beschränkt und sehen von ausgeliehenen Personen nur eingeschränkte Angaben. Nur EIN Mandant kann Heimat-Mandant sein - beim Aktivieren hier wird ein anderer automatisch deaktiviert.') }}</p>
                    </div>
                </form>

                @if (! $selectedTenant->is_active)
                    <div class="shrink-0 border-t border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {{ __('Diese Organisation ist deaktiviert: Anmeldungen sind gesperrt und alle ihre Daten sind überall ausgeblendet. Es wurde nichts gelöscht - mit „Reaktivieren“ ist alles sofort wieder da.') }}
                    </div>
                @endif
                @can('access-superadmin')
                    @unless ($selectedTenant->is_home_tenant)
                        <form x-ref="activeForm" method="POST" action="{{ route('admin.kunden.active', $selectedTenant) }}" class="hidden">
                            @csrf
                            <input type="hidden" name="active" value="{{ $selectedTenant->is_active ? 0 : 1 }}">
                        </form>
                    @endunless
                @endcan
                @unless ($selectedTenant->hasData())
                    <form x-ref="deleteForm" method="POST" action="{{ route('admin.kunden.destroy', $selectedTenant) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endunless
                <div class="shrink-0 flex items-center justify-between border-t border-gray-100 p-3">
                    <div class="flex items-center gap-2">
                        @unless ($selectedTenant->hasData())
                            <button
                                type="button"
                                @click="window.deleteWithConfirm($refs.deleteForm, {
                                    message: {{ \Illuminate\Support\Js::from(__('Dieser Kunde hat noch keine Daten und kann gefahrlos gelöscht werden.')) }},
                                })"
                                class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50"
                            >
                                {{ __('Löschen') }}
                            </button>
                        @endunless
                        @can('access-superadmin')
                            @if ($selectedTenant->hasData() && ! $selectedTenant->is_home_tenant)
                                <div x-data="{ open: false, typed: '' }" class="flex items-center gap-2">
                                    <button type="button" x-show="! open" @click="open = true; $nextTick(() => $refs.confirmName.focus())" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" title="{{ __('Löscht die Organisation mit allen Personen, Projekten und Einstellungen. Nicht rückgängig zu machen.') }}">{{ __('Endgültig löschen …') }}</button>
                                    <form x-show="open" x-cloak method="POST" action="{{ route('admin.kunden.purge', $selectedTenant) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('DELETE')
                                        <input type="text" name="confirm_name" x-ref="confirmName" x-model="typed" autocomplete="off" placeholder="{{ __('Name der Organisation eintippen') }}" class="w-56 rounded-md border-gray-300 py-1 text-xs" title="{{ __('Zur Sicherheit: Tippen Sie den Namen „:name“ ein. Danach werden die Organisation und ALLE ihre Daten unwiderruflich gelöscht.', ['name' => $selectedTenant->name]) }}">
                                        <button type="submit" :disabled="typed.trim() !== {{ \Illuminate\Support\Js::from($selectedTenant->name) }}" class="whitespace-nowrap rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40">{{ __('Alles unwiderruflich löschen') }}</button>
                                        <button type="button" @click="open = false; typed = ''" class="whitespace-nowrap rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Abbrechen') }}</button>
                                    </form>
                                </div>
                            @endif
                        @endcan
                        @can('access-superadmin')
                            @unless ($selectedTenant->is_home_tenant)
                                <button
                                    type="button"
                                    @click="(async () => {
                                        if (await window.confirmDialog({
                                            title: {{ \Illuminate\Support\Js::from($selectedTenant->is_active ? __('Organisation deaktivieren?') : __('Organisation reaktivieren?')) }},
                                            message: {{ \Illuminate\Support\Js::from($selectedTenant->is_active
                                                ? __('Wenn Sie „:name“ deaktivieren, können sich ihre Nutzer nicht mehr anmelden, und angemeldete Nutzer werden abgemeldet. Alle Daten der Organisation (Projekte, Personen, Stunden) verschwinden aus allen Listen und Auswertungen, auch ausgeliehene Personen. Es wird nichts gelöscht; mit „Reaktivieren“ ist alles sofort wieder da.', ['name' => $selectedTenant->name])
                                                : __('Wenn Sie „:name“ reaktivieren, können sich ihre Nutzer wieder anmelden, und alle Daten sind sofort wieder sichtbar.', ['name' => $selectedTenant->name])) }},
                                            confirmLabel: {{ \Illuminate\Support\Js::from($selectedTenant->is_active ? __('Deaktivieren') : __('Reaktivieren')) }},
                                            cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
                                        })) { $refs.activeForm.requestSubmit(); }
                                    })()"
                                    class="rounded-md border border-amber-300 px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50"
                                >
                                    {{ $selectedTenant->is_active ? __('Deaktivieren') : __('Reaktivieren') }}
                                </button>
                            @endunless
                        @endcan
                    </div>
                    <button type="submit" form="tenant-form-{{ $selectedTenant->id }}" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </div>
        @else
            <div class="flex flex-1 items-center justify-center p-4 text-sm text-gray-400">
                {{ __('Wähle links eine Organisation aus, um sie zu bearbeiten.') }}
            </div>
        @endif
    </div>
</div>
