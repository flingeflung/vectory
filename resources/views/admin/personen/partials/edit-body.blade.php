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
            <span class="min-w-0 truncate text-sm font-semibold text-gray-900">{{ $person->fullName() }}</span>
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
                    onclick="window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $previousPerson?->id ?? 'null' }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } }))"
                    class="{{ $navBtn }}"
                    title="{{ __('Vorherige Person') }}"
                >{!! $chevronLeft !!}</button>
                <button
                    type="button"
                    @disabled(! $nextPerson)
                    onclick="window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $nextPerson?->id ?? 'null' }}, filters: {{ \Illuminate\Support\Js::from($filters) }} } }))"
                    class="{{ $navBtn }}"
                    title="{{ __('Nächste Person') }}"
                >{!! $chevronRight !!}</button>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'person-overlay' }))"
                    class="ml-1 text-gray-400 hover:text-gray-600"
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
            <form method="POST" action="{{ route('admin.personen.update', $person) }}" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500">{{ __('ID') }}</label>
                    <input type="text" value="{{ $person->id }}" disabled class="mt-0.5 w-20 rounded-md border-gray-300 bg-gray-50 text-sm text-gray-500">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Nachname') }}</label>
                        <input type="text" id="person-last-name" name="last_name" value="{{ old('last_name', $person->last_name) }}" required oninput="window.suggestPersonShortName?.()" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Vorname') }}</label>
                        <input type="text" id="person-first-name" name="first_name" value="{{ old('first_name', $person->first_name) }}" oninput="window.suggestPersonShortName?.()" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Kürzel') }}</label>
                        <input type="text" id="person-short-name" name="short_name" value="{{ old('short_name', $person->short_name) }}" minlength="2" maxlength="4" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('E-Mail') }}</label>
                        <input type="email" name="email" value="{{ old('email', $person->email) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Firma') }}</label>
                        <select name="company_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected($person->company_id === $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Rolle') }}</label>
                        <select name="legacy_role_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($legacyRoles as $role)
                                <option value="{{ $role->id }}" @selected($person->legacy_role_id === $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Abteilung') }}</label>
                        <select name="department_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($person->department_id === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Geschäftsbereich') }}</label>
                        <select name="business_unit_id" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('– nicht zugewiesen –') }}</option>
                            @foreach ($businessUnits as $unit)
                                <option value="{{ $unit->id }}" @selected($person->business_unit_id === $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Im Unternehmen seit') }}</label>
                        <input type="date" name="start_date" value="{{ old('start_date', $person->start_date?->format('Y-m-d')) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Bis') }}</label>
                        <input type="date" name="end_date" value="{{ old('end_date', $person->end_date?->format('Y-m-d')) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-gray-500">{{ __('Bemerkungen') }}</label>
                    <textarea name="remarks" rows="3" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">{{ old('remarks', $person->remarks) }}</textarea>
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="active" value="1" @checked($person->active) class="rounded border-gray-300">
                    {{ __('Aktiv') }}
                </label>

                <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                    {{ __('Speichern') }}
                </button>
            </form>

            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('Rechte-Set') }}</div>
                <div class="text-sm text-gray-700">
                    {{ $person->permissionTemplate?->name ?? __('– nicht zugewiesen –') }}
                    <a href="{{ route('admin.rechte', ['person' => $person->id]) }}" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800">
                        {{ __('in der Rechte-Verwaltung ändern') }}
                    </a>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="mb-2 text-xs font-semibold text-gray-500">{{ __('Login-Zugang') }}</div>

                @if ($person->user)
                    <div class="mb-3 text-sm text-gray-700">
                        {{ __('Benutzername') }}: <span class="font-medium">{{ $person->user->username }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.personen.password.reset', $person) }}" class="flex items-end gap-2">
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Neues Passwort') }}</label>
                            <input type="text" name="password" required minlength="4" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        </div>
                        <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ __('Speichern') }}
                        </button>
                    </form>
                @else
                    <div class="mb-2 text-xs text-gray-400">{{ __('Diese Person hat noch keinen Login-Zugang (reine Kontaktperson).') }}</div>
                    <form method="POST" action="{{ route('admin.personen.login.store', $person) }}" class="flex flex-wrap items-end gap-2">
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
                            <input type="text" name="password" required minlength="4" class="mt-0.5 rounded-md border-gray-300 text-sm">
                        </div>
                        <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                            {{ __('Login-Zugang anlegen') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
