<x-admin-layout>
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

    <div class="mb-3 shrink-0">
        <a href="{{ route('admin.personen') }}" class="text-sm text-gray-500 hover:text-gray-700">&laquo; {{ __('Zur Liste') }}</a>
    </div>

    <div class="max-w-2xl flex-1 min-h-0 space-y-4 overflow-y-auto">
        <form method="POST" action="{{ route('admin.personen.update', $person) }}" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Nachname') }}</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $person->last_name) }}" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Vorname') }}</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $person->first_name) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">{{ __('E-Mail') }}</label>
                    <input type="email" name="email" value="{{ old('email', $person->email) }}" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                </div>
                <div></div>
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
                <div></div>
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

            @if ($person->legacyRole)
                <div class="text-xs text-gray-400">{{ __('Importierte Vietto-Rolle (nur Referenz)') }}: {{ $person->legacyRole->name }}</div>
            @endif

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
                        <label class="block text-xs text-gray-500">{{ __('Neues Passwort setzen') }}</label>
                        <input type="text" name="password" required minlength="4" class="mt-0.5 rounded-md border-gray-300 text-sm">
                    </div>
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        {{ __('Passwort setzen') }}
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
</x-admin-layout>
