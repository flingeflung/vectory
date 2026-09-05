<x-admin-layout>
    @if (session('status') === 'rechte-updated')
        <div class="mb-3 shrink-0 rounded bg-green-50 px-3 py-2 text-sm text-green-700">{{ __('Gespeichert.') }}</div>
    @endif

    <div class="flex flex-1 min-h-0 gap-4">
        {{-- Links: WEN - erst Funktionsgruppen, dann alle Personen. --}}
        <div x-data="{ search: '', showInactive: false }" class="flex w-72 shrink-0 flex-col rounded-lg border border-gray-200 bg-white">
            <div class="shrink-0 space-y-1.5 border-b border-gray-100 p-2">
                <input
                    type="search"
                    x-model="search"
                    placeholder="{{ __('Schnellsuche (Nachname)') }}"
                    class="w-full rounded-md border-gray-300 py-1 text-sm"
                >
                <label class="flex items-center gap-1.5 text-xs text-gray-600">
                    <input type="checkbox" x-model="showInactive" class="rounded border-gray-300">
                    {{ __('Inaktive Personen zeigen') }}
                </label>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-2 text-sm">
                <div class="px-1 py-1 text-xs font-semibold text-gray-500">{{ __('Funktionsgruppen') }}</div>
                @foreach ($functionGroups as $group)
                    <a
                        href="{{ route('admin.rechte', ['group' => $group->id]) }}"
                        class="block rounded px-2 py-1 {{ $selectedGroup?->id === $group->id ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}"
                    >
                        {{ $group->name }}
                    </a>
                @endforeach

                <div class="mt-3 px-1 py-1 text-xs font-semibold text-gray-500">{{ __('Personen') }}</div>
                @foreach ($people as $person)
                    <a
                        href="{{ route('admin.rechte', ['person' => $person->id]) }}"
                        x-show="(showInactive || {{ $person->active || $selectedPerson?->id === $person->id ? 'true' : 'false' }}) && (!search || {{ \Illuminate\Support\Js::from(mb_strtolower($person->fullName())) }}.includes(search.toLowerCase()))"
                        class="block rounded px-2 py-1 {{ $selectedPerson?->id === $person->id ? 'bg-indigo-50 font-medium text-indigo-700' : ($person->active ? 'text-gray-700 hover:bg-gray-50' : 'text-gray-400 hover:bg-gray-50') }}"
                    >
                        {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Rechts: WAS - eine gemeinsame Liste für alle Rechte (auch künftige
             Navigations-Rechte wie "Admin sehen") statt zwei getrennter Spalten
             wie in Vietto. Noch ohne Kategorien - kommt, sobald der Katalog
             wächst und eine flache Liste unübersichtlich wird. --}}
        <div x-data="{ search: '' }" class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white">
            @if ($selectedGroup || $selectedPerson)
                <div class="shrink-0 flex items-center justify-between gap-3 border-b border-gray-100 p-3">
                    <div class="text-sm font-medium text-gray-900">
                        {{ $selectedGroup?->name ?? $selectedPerson->fullName() }}
                        <span class="ml-1 text-xs font-normal text-gray-400">
                            {{ $selectedGroup ? __('(Funktionsgruppe – Rechte-Vorlage)') : __('(Person – individuelle Ausnahmen)') }}
                        </span>
                    </div>
                    <input
                        type="search"
                        x-model="search"
                        placeholder="{{ __('Filter (Rechte)') }}"
                        class="w-48 rounded-md border-gray-300 py-1 text-sm"
                    >
                </div>

                <form
                    method="POST"
                    action="{{ $selectedGroup ? route('admin.rechte.funktionsgruppen.update', $selectedGroup) : route('admin.rechte.personen.update', $selectedPerson) }}"
                    class="flex flex-1 min-h-0 flex-col"
                >
                    @csrf
                    <div class="flex-1 min-h-0 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($permissions as $permission)
                                    <tr x-show="!search || {{ \Illuminate\Support\Js::from(mb_strtolower($permission->label)) }}.includes(search.toLowerCase())">
                                        <td class="px-3 py-2 text-gray-700" title="{{ $permission->key }}">{{ $permission->label }}</td>
                                        @if ($selectedGroup)
                                            <td class="w-10 px-3 py-2 text-center">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $permission->id }}"
                                                    class="rounded border-gray-300"
                                                    @checked($grantedPermissionIds->contains($permission->id))
                                                >
                                            </td>
                                        @else
                                            @php $current = $personOverrides->get($permission->id)?->pivot->granted; @endphp
                                            <td class="w-20 px-2 py-2 text-center text-xs text-gray-400">
                                                <input type="radio" name="override[{{ $permission->id }}]" value="" @checked($current === null)>
                                                {{ __('Vorlage') }}
                                            </td>
                                            <td class="w-20 px-2 py-2 text-center text-xs text-gray-400">
                                                <input type="radio" name="override[{{ $permission->id }}]" value="1" @checked($current === true)>
                                                {{ __('Zusätzlich') }}
                                            </td>
                                            <td class="w-20 px-2 py-2 text-center text-xs text-gray-400">
                                                <input type="radio" name="override[{{ $permission->id }}]" value="0" @checked($current === false)>
                                                {{ __('Entzogen') }}
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="shrink-0 border-t border-gray-100 p-3">
                        <button type="submit" class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                            {{ __('Speichern') }}
                        </button>
                    </div>
                </form>
            @else
                <div class="flex flex-1 items-center justify-center text-sm text-gray-400">
                    {{ __('Links eine Funktionsgruppe oder Person auswählen.') }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
