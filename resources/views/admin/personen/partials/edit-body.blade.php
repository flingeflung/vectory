@php
    $isOverlay = $overlay ?? false;
    $filters = $filters ?? [];

    // Gleicher Button-Look wie beim Projekt-Overlay (Konsistenz).
    $navBtn = 'p-1.5 rounded hover:bg-gray-100 hover:text-gray-800 disabled:opacity-25 disabled:hover:bg-transparent disabled:hover:text-gray-500';
    $chevronLeft = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>';
    $chevronRight = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>';
@endphp

<div class="{{ $isOverlay ? 'flex h-full min-h-0 flex-col' : '' }}">
    {{-- Kopf/Verschiebebalken: fix stehend, trägt Titel + Vor/Zurück-
         Blättern innerhalb der aktuell gefilterten Personenliste (gleiches
         Muster wie beim Projekt-Overlay) + Schließen-Button (Overlay) bzw.
         "Zur Liste" (Vollseite). --}}
    <div
        class="flex items-center justify-between gap-2 {{ $isOverlay ? 'shrink-0 cursor-move select-none rounded-t-lg border-b border-gray-200 bg-gray-100 px-4 py-2' : 'mb-3' }}"
        @if ($isOverlay) data-drag-handle title="{{ __('Ziehen zum Verschieben') }}" @endif
    >
        @if ($isOverlay)
            <span class="min-w-0 truncate text-sm font-semibold text-gray-900">{{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }} <x-absence-icon :person="$person" /></span>
        @else
            <a href="{{ route('admin.personen') }}" class="text-sm text-gray-500 hover:text-gray-700">&laquo; {{ __('Zur Liste') }}</a>
        @endif

        {{-- Feste rechte Gruppe (Pfeile + Schließen) - unabhängig von der
             Namenslänge an derselben Stelle, hüpft beim Blättern nicht mehr
             hin und her (anders als vorher, als die Pfeile direkt neben dem
             unterschiedlich langen Namen standen). --}}
        <div class="flex shrink-0 items-center gap-1 text-gray-500">
            @if ($isOverlay)
                <button
                    type="button"
                    @disabled(! $previousPerson)
                    onclick="window.confirmDiscardIfDirty('personOverlayIsDirty').then(ok => ok && window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $previousPerson?->id ?? 'null' }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } })))"
                    class="{{ $navBtn }}"
                    title="{{ __('Vorherige Person') }}"
                >{!! $chevronLeft !!}</button>
                <button
                    type="button"
                    @disabled(! $nextPerson)
                    onclick="window.confirmDiscardIfDirty('personOverlayIsDirty').then(ok => ok && window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $nextPerson?->id ?? 'null' }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } })))"
                    class="{{ $navBtn }}"
                    title="{{ __('Nächste Person') }}"
                >{!! $chevronRight !!}</button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'person-overlay' }))"
                    class="ml-8 text-gray-400 hover:text-gray-600"
                    aria-label="{{ __('Schließen') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            @else
                <a
                    href="{{ $previousPerson ? route('admin.personen.edit', [...$filters, 'person' => $previousPerson->id]) : '#' }}"
                    class="{{ $navBtn }} {{ ! $previousPerson ? 'pointer-events-none opacity-25' : '' }}"
                    title="{{ __('Vorherige Person') }}"
                >{!! $chevronLeft !!}</a>
                <a
                    href="{{ $nextPerson ? route('admin.personen.edit', [...$filters, 'person' => $nextPerson->id]) : '#' }}"
                    class="{{ $navBtn }} {{ ! $nextPerson ? 'pointer-events-none opacity-25' : '' }}"
                    title="{{ __('Nächste Person') }}"
                >{!! $chevronRight !!}</a>
            @endif
        </div>
    </div>

    <div class="{{ $isOverlay ? 'min-h-0 flex-1 overflow-y-auto px-4 py-3' : '' }}">
        @if (session('status'))
            <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
                @switch(session('status'))
                    @case('login-created')
                        {{ __('Login-Zugang angelegt.') }}
                        @break
                    @case('password-reset')
                        {{ __('Passwort gesetzt.') }}
                        @break
                    @case('activation-sent')
                        {{ __('Aktivierungslink gesendet.') }}
                        @break
                    @case('password-link-sent')
                        {{ __('Link zum Zurücksetzen des Passworts gesendet.') }}
                        @break
                    @case('logged-out')
                        {{ __('Die Person wurde abgemeldet.') }}
                        @break
                    @case('role-updated')
                        {{ __('Rolle geändert.') }}
                        @break
                    @default
                        {{ __('Gespeichert.') }}
                @endswitch
            </x-flash-message>
        @endif

        @if ($errors->any())
            <div class="mb-3 shrink-0 rounded bg-red-50 px-3 py-2 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="{{ $isOverlay ? '' : 'max-w-2xl' }} space-y-4">
            @unless ($canFullyEdit)
                {{--
                    Ausgeliehene Person, angesehen von einem Kundekunde-Admin
                    (Ralf, 2026-09-18): nur ein eingeschränkter Satz an
                    Angaben sichtbar, nichts davon bearbeitbar - das ist eine
                    Mandanten-Grenze zwischen unterschiedlichen zahlenden
                    Kunden, bewusst hart hier verdrahtet statt über das
                    Rechte-System lösbar (siehe PersonController::
                    personFullyEditableByCurrentUser()).
                --}}
                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                    <p class="text-xs text-gray-400">{{ __('Diese Person gehört zu :tenant und ist hier nur ausgeliehen - nur eingeschränkte Angaben sichtbar, keine Bearbeitung möglich.', ['tenant' => $person->tenant?->name ?? '–']) }}</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="block text-xs text-gray-500">{{ __('Name') }}</span>
                            {{ $person->fullName() }}{{ $person->short_name ? ' ('.$person->short_name.')' : '' }}
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">{{ __('E-Mail') }}</span>
                            {{ $person->email ?: '–' }}
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">{{ __('Abteilung') }}</span>
                            {{ $person->department?->name ?? '–' }}
                        </div>
                        <div>
                            <span class="block text-xs text-gray-500">{{ __('Rolle') }}</span>
                            {{ $person->legacyRole?->name ?? '–' }}
                        </div>
                        <div class="col-span-2">
                            <span class="block text-xs text-gray-500">{{ __('Status') }}</span>
                            {{ $person->active ? __('Aktiv') : __('Inaktiv') }}
                            @if ($person->isCurrentlyAbsent())
                                · {{ __('Abwesend') }}
                            @endif
                        </div>
                    </div>
                </div>
            @else
            <form
                method="POST"
                action="{{ route('admin.personen.update', $person) }}"
                x-data="{ dirty: false }"
                @input="dirty = window.formIsDirty($el)"
                class="space-y-4 rounded-lg border border-gray-200 bg-white p-4"
            >
                @if (\App\Models\SystemSetting::multiTenantEnabled())
                    <p class="-mt-1 text-xs text-gray-400">{{ __('Gültig für Organisation: :tenant', ['tenant' => $person->tenant?->name ?? '–']) }}</p>
                @endif
                <div class="flex items-end gap-4">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('ID') }}</label>
                        <input type="text" value="{{ $person->id }}" disabled class="mt-0.5 w-20 rounded-md border-gray-300 bg-gray-50 py-1.5 text-sm text-gray-500">
                    </div>
                    <label class="mb-1.5 flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-sm font-medium text-gray-700">
                        <input type="checkbox" name="active" value="1" @checked($person->active) class="rounded border-gray-300">
                        {{ __('Aktiv') }}
                    </label>
                    @if ($person->user && $person->user->mayLogIn())
                        <button type="submit" form="force-logout-form" class="mb-1.5 rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Beendet alle laufenden Sitzungen der Person sofort. Sie muss sich neu anmelden. Soll die Person auch danach nicht wieder hinein, entfernen Sie zusätzlich das Häkchen bei „Aktiv“ und speichern.') }}">{{ __('Sofort abmelden') }}</button>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Nachname') }}</label>
                        <input
                            type="text"
                            id="person-last-name"
                            name="last_name"
                            value="{{ old('last_name', $person->last_name) }}"
                            required
                            oninput="window.suggestPersonShortName?.()"
                            {{-- Direkt beim Anlegen ist last_name mit dem Platzhalter "Neue
                                 Person" vorbelegt (siehe PersonController::store()) - Ralf
                                 musste den bisher immer erst manuell rauslöschen. Markierung
                                 hier, tatsächliches Markieren/Fokussieren übernimmt JS (siehe
                                 layouts/app.blade.php loadPerson() fürs Overlay, Script unten
                                 für den Vollseiten-Fallback ohne Overlay-Kontext). --}}
                            data-select-on-load="{{ $person->first_name === '' && $person->last_name === __('Neue Person') ? '1' : '0' }}"
                            class="mt-0.5 w-full rounded-md border-gray-300 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Vorname') }}</label>
                        <input type="text" id="person-first-name" name="first_name" value="{{ old('first_name', $person->first_name) }}" oninput="window.suggestPersonShortName?.()" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                        <input type="text" id="person-short-name" name="short_name" value="{{ old('short_name', $person->short_name) }}" minlength="2" maxlength="4" data-check-url="{{ route('admin.personen.feldpruefung', $person) }}" onblur="window.checkPersonField?.(this)" oninput="window.clearPersonFieldHint?.(this)" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <p data-field-hint class="mt-0.5 hidden text-xs text-red-600"></p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('E-Mail') }}</label>
                        <input type="email" name="email" value="{{ old('email', $person->email) }}" data-check-url="{{ route('admin.personen.feldpruefung', $person) }}" onblur="window.checkPersonField?.(this)" oninput="window.clearPersonFieldHint?.(this)" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <p data-field-hint class="mt-0.5 hidden text-xs text-red-600"></p>
                    </div>
                    @if ($multiTenantEnabled)
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Organisation') }}</label>
                            <div class="mt-0.5 w-full rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-sm text-gray-600" title="{{ $person->tenant?->name }}">
                                {{ $person->tenant?->short_name ?? $person->tenant?->name ?? '–' }}
                            </div>
                        </div>
                    @endif
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs text-gray-500">{{ \App\Models\SystemSetting::companyLabel() }}</label>
                            <button
                                type="button"
                                onclick="window.__personOverlayTenantId = {{ $person->tenant_id }}; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'company-manager' }))"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >{{ __('verwalten') }}</button>
                        </div>
                        <select id="person-company-id" name="company_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected($person->company_id === $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs text-gray-500">{{ __('Rolle') }}</label>
                            <button
                                type="button"
                                onclick="window.__personOverlayTenantId = {{ $person->tenant_id }}; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'legacy-role-manager' }))"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >{{ __('verwalten') }}</button>
                        </div>
                        <select id="person-legacy-role-id" name="legacy_role_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($legacyRoles as $role)
                                <option value="{{ $role->id }}" @selected($person->legacy_role_id === $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs text-gray-500">{{ __('Abteilung') }}</label>
                            <button
                                type="button"
                                onclick="window.__personOverlayTenantId = {{ $person->tenant_id }}; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'department-manager' }))"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >{{ __('verwalten') }}</button>
                        </div>
                        <select id="person-department-id" name="department_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($person->department_id === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs text-gray-500">{{ __('Geschäftsbereich') }}</label>
                            <button
                                type="button"
                                onclick="window.__personOverlayTenantId = {{ $person->tenant_id }}; window.dispatchEvent(new CustomEvent('open-modal', { detail: 'business-unit-manager' }))"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >{{ __('verwalten') }}</button>
                        </div>
                        <select id="person-business-unit-id" name="business_unit_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($businessUnits as $unit)
                                <option value="{{ $unit->id }}" @selected($person->business_unit_id === $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs text-gray-500">{{ __('Rechte-Gruppe') }}</label>
                            <a
                                href="{{ route('admin.rechte', ['person' => $person->id]) }}"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >{{ __('verwalten') }}</a>
                        </div>
                        <select id="person-permission-template-id" name="permission_template_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($permissionTemplates as $template)
                                <option value="{{ $template->id }}" @selected($person->permission_template_id === $template->id)>{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs text-gray-500">{{ __('Funktionsgruppe(n)') }}</label>
                            <a
                                href="{{ route('admin.function-groups', ['person' => $person->id]) }}"
                                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                            >{{ __('verwalten') }}</a>
                        </div>
                        {{-- Ralf, 2026-09-12: ohne Funktionsgruppen-Zuordnung
                             taucht eine Person nirgends in den
                             Workflow-Schritten auf - deshalb direkt hier statt
                             nur über die separate Funktionsgruppen-Seite. --}}
                        <select id="person-function-group-ids" name="function_group_ids[]" multiple size="4" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            @foreach ($functionGroups as $group)
                                <option value="{{ $group->id }}" @selected($person->functionGroups->contains('id', $group->id))>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Ralf, 2026-09-28: "Im Unternehmen seit"/"Bis" in einer eigenen,
                     festen 2er-Reihe statt im fortlaufenden Grid oben - dort hätte
                     ihre Spalten-Position (und ob sie überhaupt in einer Zeile
                     zusammenstehen) von der Anzahl der Felder davor abgehangen,
                     genau das brach beim Entfernen des alten Wochenstunden-Feldes
                     (siehe Ralf-Bug-Report 2026-09-28). --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Im Unternehmen seit') }}</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $person->start_date?->format('Y-m-d')) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Bis') }}</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $person->end_date?->format('Y-m-d')) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                {{-- Ralf, 2026-09-28: Wochenstunden UND Urlaubstage jetzt historisiert
                     statt einzelner Werte (Grundlage der personellen
                     Ressourcenplanung - beides kann sich im Zeitverlauf ändern).
                     Nur lesend hier, Pflege im jeweils eigenen Overlay
                     ("verwalten"), löst das bisherige Feld person.weekly_hours ab.
                     Nur für Personen mit Login relevant (nur die buchen Stunden).
                     Eigene 2er-Reihe wie "Im Unternehmen seit"/"Bis" oben, aus
                     demselben Grund. --}}
                @if ($person->user)
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-xs text-gray-500">{{ __('Wochenstunden') }}</label>
                                <button
                                    type="button"
                                    onclick="window.openPersonWeeklyHours({{ $person->id }})"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                                >{{ __('verwalten') }}</button>
                            </div>
                            <div id="person-weekly-hours-display" class="mt-0.5 w-full rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-sm text-gray-600">
                                {{ $person->currentWeeklyHours() !== null ? rtrim(rtrim(number_format((float) $person->currentWeeklyHours(), 1, '.', ''), '0'), '.').' h' : '–' }}
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="block text-xs text-gray-500">{{ __('Urlaubstage') }}</label>
                                <button
                                    type="button"
                                    onclick="window.openPersonVacationDays({{ $person->id }})"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                                >{{ __('verwalten') }}</button>
                            </div>
                            <div id="person-vacation-days-display" class="mt-0.5 w-full rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-sm text-gray-600">
                                {{ $person->currentVacationDays() !== null ? rtrim(rtrim(number_format((float) $person->currentVacationDays(), 1, '.', ''), '0'), '.') : '–' }}
                            </div>
                        </div>
                    </div>

                    {{-- Ralf, 2026-09-29: Abteilungsfilter in der Planung ersatzlos
                         entfernt (funktionierte nicht zuverlässig für alle
                         Szenarien) - stattdessen direkt hier je Person markieren,
                         ob sie einbezogen wird (z.B. um externe Dienstleister wie
                         Florian auszuschließen). --}}
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700" title="{{ __('Diese Person in die Ressourcenplanung mit einbeziehen') }}">
                            <input type="checkbox" name="resource_planning" value="1" @checked(old('resource_planning', $person->resource_planning)) class="rounded border-gray-300">
                            {{ __('Ressourcenplanung') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700" title="{{ __('Die Person ist im Kalender sichtbar und kann dort eigene Einträge machen') }}">
                            <input type="checkbox" name="calendar_enabled" value="1" @checked(old('calendar_enabled', $person->calendar_enabled)) class="rounded border-gray-300">
                            {{ __('Kalender') }}
                        </label>
                    </div>
                @endif

                <div>
                    <label class="block text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
                    <textarea name="remarks" rows="3" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">{{ old('remarks', $person->remarks) }}</textarea>
                </div>

                @csrf
                {{-- Sticky statt fest am Ende: das Formular hier ist oft länger
                     als der sichtbare Bereich (Ralf: Speichern-Button "nicht im
                     Standard-Sichtbereich"). Bleibt beim Scrollen durch DIESES
                     Formular am unteren Rand kleben, verschwindet aber wieder
                     normal, sobald man weiter zu den Boxen darunter
                     (Rechte-Set, Login-Zugang, ...) scrollt - kein separates
                     Fixed-Footer-Layout nötig, das den Rest umbauen würde. --}}
                <div x-show="dirty" x-cloak class="sticky bottom-0 -mx-4 -mb-4 rounded-b-lg border-t border-gray-200 bg-white px-4 py-3">
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </form>
            @endunless

            @if ($canFullyEdit)
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('Login-Zugang') }}</div>

                @php
                    $accountUser = $person->user;
                    $accountPending = $accountUser?->isPending();
                    $canSetPassword = auth()->user()->can('access-superadmin');
                    $activationToken = $accountPending ? \App\Models\AccountActivationToken::query()->where('user_id', $accountUser->id)->first() : null;
                @endphp

                @if ($accountUser && $accountPending)
                    {{-- Vorbereitetes Konto: wartet auf die Aktivierung durch die Person (Ralf, 2026-10-08) --}}
                    <div class="mb-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                        {{ __('Zugang vorbereitet für :email – wartet auf die Aktivierung durch die Person.', ['email' => $accountUser->email]) }}
                        @if ($activationToken)
                            <span class="block text-xs">
                                {{ __('Link gesendet am :date, gültig bis :until.', ['date' => $activationToken->updated_at->format('d.m.Y H:i'), 'until' => $activationToken->expires_at->format('d.m.Y H:i')]) }}
                                @if ($activationToken->expires_at->isPast()) <b>{{ __('Abgelaufen.') }}</b> @endif
                            </span>
                        @endif
                    </div>
                    @if (\App\Services\ActivationLinkSender::eligible($accountUser->load('person')))
                        <form method="POST" action="{{ route('admin.personen.activation.send', $person) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Aktivierungslink erneut senden') }}</button>
                        </form>
                    @endif
                @elseif ($accountUser)
                    <div class="mb-3 text-sm text-gray-700">
                        {{ __('Benutzername') }}: <span class="font-medium">{{ $accountUser->username }}</span>
                        <span class="block text-xs text-gray-400">{{ $accountUser->email }}@if ($accountUser->activated_at) · {{ __('aktiv seit :date', ['date' => $accountUser->activated_at->format('d.m.Y')]) }}@endif</span>
                    </div>
                    <form method="POST" action="{{ route('admin.personen.password.link', $person) }}" class="mb-2">
                        @csrf
                        <button type="submit" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover" title="{{ __('Sendet der Person einen Link, über den sie selbst ein neues Passwort wählt.') }}">{{ __('Link zum Zurücksetzen des Passworts senden') }}</button>
                    </form>
                    <form id="force-logout-form" method="POST" action="{{ route('admin.personen.force-logout', $person) }}"
                          x-data="{ async submitLogout(e) {
                              if (await window.confirmDialog({
                                  signal: 'achtung',
                                  title: {{ \Illuminate\Support\Js::from(__('Person sofort abmelden?')) }},
                                  message: {{ \Illuminate\Support\Js::from(__('Die Person wird sofort aus Vectory abgemeldet.')) }},
                                  consequence: {{ \Illuminate\Support\Js::from(__('Wenn die Person gerade arbeitet, gehen nicht gespeicherte Eingaben verloren. Sie kann sich mit ihren Zugangsdaten sofort wieder anmelden.')) }},
                                  confirmLabel: {{ \Illuminate\Support\Js::from(__('Abmelden')) }},
                                  cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
                              })) { e.target.submit(); }
                          } }"
                          @submit.prevent="submitLogout($event)">
                        @csrf
                    </form>
                    @if ($canSetPassword)
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs text-gray-500">{{ __('Ohne E-Mail: Passwort selbst festlegen (nur Super-Admin)') }}</summary>
                            <form method="POST" action="{{ route('admin.personen.password.reset', $person) }}" x-data="{ dirty: false }" @input="dirty = window.formIsDirty($el)" class="mt-2 flex items-end gap-2">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-500">{{ __('Neues Passwort') }}</label>
                                    <input type="text" name="password" required class="mt-0.5 rounded-md border-gray-300 text-sm">
                                    <p class="mt-0.5 text-xs text-gray-400">{{ \App\Support\PasswordPolicy::hint() }}</p>
                                </div>
                                <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                                    {{ __('Speichern') }}
                                </button>
                            </form>
                        </details>
                    @endif
                @else
                    <div class="mb-2 text-xs text-gray-400">{{ __('Diese Person hat noch keinen Login-Zugang (reine Kontaktperson). Die Person wählt Benutzername und Passwort selbst, nachdem sie den Aktivierungslink erhalten hat.') }}</div>
                    @if ($person->active)
                        <form method="POST" action="{{ route('admin.personen.account.prepare', $person) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <div>
                                <label class="block text-xs text-gray-500">{{ __('E-Mail für den Aktivierungslink') }}</label>
                                <input type="email" name="email" value="{{ old('email', $person->email) }}" required class="mt-0.5 w-72 rounded-md border-gray-300 text-sm">
                            </div>
                            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                                {{ __('Zugang vorbereiten und Link senden') }}
                            </button>
                        </form>
                    @else
                        <div class="text-xs text-amber-700">{{ __('Für eine inaktive Person lässt sich kein Zugang vorbereiten.') }}</div>
                    @endif
                    @if ($canSetPassword)
                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs text-gray-500">{{ __('Ohne E-Mail: Benutzername und Passwort selbst festlegen (nur Super-Admin)') }}</summary>
                            <form method="POST" action="{{ route('admin.personen.login.store', $person) }}" x-data="{ dirty: false }" @input="dirty = window.formIsDirty($el)" class="mt-2 flex flex-wrap items-end gap-2">
                                @csrf
                                <div>
                                    <label class="block text-xs text-gray-500">{{ __('Benutzername') }}</label>
                                    <input type="text" name="username" required class="mt-0.5 rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">{{ __('E-Mail') }}</label>
                                    <input type="email" name="email" value="{{ $person->email }}" required class="mt-0.5 rounded-md border-gray-300 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">{{ __('Passwort') }}</label>
                                    <input type="text" name="password" required class="mt-0.5 rounded-md border-gray-300 text-sm">
                                </div>
                                <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                                    {{ __('Login-Zugang anlegen') }}
                                </button>
                            </form>
                        </details>
                    @endif
                @endif
            </div>

            @if ($actingUserCanManageAccessLevel && $person->user)
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('Zugriffsstufe') }}</div>
                    <p class="mb-2 text-xs text-gray-400">{{ __('Die Zugriffsstufe legt den administrativen Bereich fest und ist unabhängig von der fachlichen Rolle und dem Rechte-Set.') }}</p>
                    <div class="flex items-end justify-between gap-3">
                        <form
                            method="POST"
                            action="{{ route('admin.personen.role.update', $person) }}"
                            class="flex flex-1 items-end gap-2"
                            x-data="{ dirty: false, async save(e) { if (await window.confirmDialog({ title: {{ \Illuminate\Support\Js::from(__('Zugriffsstufe ändern?')) }}, message: {{ \Illuminate\Support\Js::from(__('Die neue Zugriffsstufe verändert den administrativen Zugriff dieser Person.')) }}, confirmLabel: {{ \Illuminate\Support\Js::from(__('Speichern')) }}, cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }} })) e.target.submit(); } }"
                            @change="dirty = true"
                            @submit.prevent="save($event)"
                        >
                            @csrf
                            <select name="access_level" class="min-w-48 rounded-md border-gray-300 text-sm">
                                <option value="user" @selected($person->user->role === 'user')>{{ __('User') }}</option>
                                @if (! $person->tenant?->is_home_tenant)
                                    <option value="organization_admin" @selected($person->user->role === 'organization_admin')>{{ __('Organisations-Admin') }}</option>
                                @endif
                                @if ($actingUserIsSuperAdmin && $person->tenant?->is_home_tenant)
                                    <option value="central_admin" @selected($person->user->role === 'central_admin')>{{ __('Zentral-Admin') }}</option>
                                @endif
                                @if ($actingUserIsSuperAdmin)
                                    <option value="super_admin" @selected($person->user->role === 'super_admin')>{{ __('Super-Admin') }}</option>
                                @endif
                            </select>
                            <button x-show="dirty" x-cloak type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($multiTenantEnabled)
                <div class="rounded-lg border border-gray-200 bg-white p-4" x-data="{ dirty: false }">
                    <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('Organisationszugriff') }}</div>
                    <p class="mb-2 text-xs text-gray-400">{{ __('Zusätzliche Organisationen, auf die diese Person umschalten darf (neben ihrer eigenen Organisation). Ihr Rechte-Set bleibt dabei immer das ihrer eigenen Organisation – in jeder freigegebenen Organisation gleich, unabhängig davon, welche Rechte-Sets diese Organisation selbst definiert hat.') }}</p>
                    <form method="POST" action="{{ route('admin.personen.tenant-access.update', $person) }}" @input="dirty = window.formIsDirty($el)" class="space-y-2">
                        @csrf
                        @forelse ($otherTenants as $tenant)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    name="tenant_ids[]"
                                    value="{{ $tenant->id }}"
                                    @checked($person->accessibleTenants->contains('id', $tenant->id))
                                    class="rounded border-gray-300"
                                >
                                {{ $tenant->name }}
                            </label>
                        @empty
                            <div class="text-sm text-gray-400">{{ __('Noch keine weiteren Organisationen angelegt.') }}</div>
                        @endforelse
                        @if ($otherTenants->isNotEmpty())
                            <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                                {{ __('Speichern') }}
                            </button>
                        @endif
                    </form>
                </div>
            @endif

            @unless ($person->hasData())
                <div x-data="{ confirming: false }" class="rounded-lg border border-gray-200 bg-white p-4">
                    <div x-show="!confirming" class="flex justify-end">
                        <button type="button" @click="confirming = true" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">
                            {{ __('Person löschen') }}
                        </button>
                    </div>
                    <form x-show="confirming" x-cloak data-delete-form method="POST" action="{{ route('admin.personen.destroy', $person) }}" class="flex items-center justify-between gap-2">
                        @csrf
                        @method('DELETE')
                        <span class="text-xs text-gray-400">{{ __('Diese Person hat noch keine Daten (Projekte, Aufgaben, Login) und kann gefahrlos gelöscht werden.') }}</span>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" @click="confirming = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                            <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Endgültig löschen') }}</button>
                        </div>
                    </form>
                </div>
            @endunless
            @endif
        </div>
    </div>
</div>

@unless ($isOverlay)
    {{-- Overlay-Fall wird in layouts/app.blade.php (loadPerson()) erledigt -
         dieses <script> läuft nur bei echtem Seitenaufruf, nicht wenn dieses
         Partial per fetch()/innerHTML in den Overlay eingesetzt wird
         (eingefügte <script>-Tags sind dann inert). --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var lastNameInput = document.getElementById('person-last-name');
            if (lastNameInput && lastNameInput.dataset.selectOnLoad === '1') {
                lastNameInput.focus();
                lastNameInput.select();
            }
        });
    </script>
@endunless
