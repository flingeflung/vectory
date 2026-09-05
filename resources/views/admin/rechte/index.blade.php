<x-admin-layout>
    <div class="flex-1 min-h-0 overflow-y-auto space-y-8 pb-8">

        @if (session('status') === 'rechte-updated')
            <div class="rounded bg-green-50 px-3 py-2 text-sm text-green-700">{{ __('Gespeichert.') }}</div>
        @endif

        <section>
            <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ __('Rechte-Vorlage je Funktionsgruppe') }}</h3>
            <p class="mb-3 text-xs text-gray-500">
                {{ __('Mitglieder einer Funktionsgruppe bekommen deren Rechte standardmäßig - individuelle Ausnahmen weiter unten.') }}
            </p>

            <form method="POST" action="{{ route('admin.rechte.funktionsgruppen') }}">
                @csrf
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Funktionsgruppe') }}</th>
                                @foreach ($permissions as $permission)
                                    <th class="px-4 py-2 text-left font-medium text-gray-500" title="{{ $permission->key }}">{{ $permission->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($functionGroups as $group)
                                @php $grantedIds = $group->permissions->pluck('id'); @endphp
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-gray-700">{{ $group->name }}</td>
                                    @foreach ($permissions as $permission)
                                        <td class="px-4 py-2">
                                            <input
                                                type="checkbox"
                                                name="permissions[{{ $group->id }}][]"
                                                value="{{ $permission->id }}"
                                                class="rounded border-gray-300"
                                                @checked($grantedIds->contains($permission->id))
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $permissions->count() + 1 }}" class="px-4 py-6 text-center text-gray-500">{{ __('Keine Funktionsgruppen vorhanden.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="mt-3 rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                    {{ __('Speichern') }}
                </button>
            </form>
        </section>

        <section>
            <h3 class="mb-1 text-sm font-semibold text-gray-900">{{ __('Individuelle Ausnahmen') }}</h3>
            <p class="mb-3 text-xs text-gray-500">
                {{ __('Für eine einzelne Person ein Recht zusätzlich gewähren (auch ohne passende Funktionsgruppe) oder entziehen (auch wenn eine Funktionsgruppe es gewähren würde).') }}
            </p>

            <form method="GET" action="{{ route('admin.rechte') }}" class="mb-4">
                <label class="flex items-center gap-1.5 text-sm">
                    <span class="text-gray-500">{{ __('Person') }}:</span>
                    <select name="person" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                        <option value="">{{ __('– auswählen –') }}</option>
                        @foreach ($people as $person)
                            <option value="{{ $person->id }}" @selected($selectedPerson?->id === $person->id) @class(['text-gray-400' => ! $person->active])>
                                {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </form>

            @if ($selectedPerson)
                <form method="POST" action="{{ route('admin.rechte.personen.update', $selectedPerson) }}" class="max-w-xl">
                    @csrf
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500">{{ __('Recht') }}</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('Vorlage') }}</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('Zusätzlich gewährt') }}</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-500">{{ __('Entzogen') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($permissions as $permission)
                                    @php $current = $personOverrides->get($permission->id)?->pivot->granted; @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700" title="{{ $permission->key }}">{{ $permission->label }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="radio" name="override[{{ $permission->id }}]" value="" @checked($current === null)>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="radio" name="override[{{ $permission->id }}]" value="1" @checked($current === true)>
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <input type="radio" name="override[{{ $permission->id }}]" value="0" @checked($current === false)>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="mt-3 rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                        {{ __('Speichern') }}
                    </button>
                </form>
            @endif
        </section>
    </div>
</x-admin-layout>
