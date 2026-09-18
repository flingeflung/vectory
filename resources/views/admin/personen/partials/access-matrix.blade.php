{{--
    Kundenzugriff-Matrix (Ralf, 2026-09-18) - Personen genau wie in der
    aktuell gefilterten/sortierten Personenliste (kein eigener Filter
    hier), Spalten: Heimat-Mandant zuerst, dann alle anderen Kunden
    alphabetisch. Reine Anzeige, keine Bearbeitung (die bleibt bei der
    einzelnen Person). Sticky erste Spalte (Name), gleiches Muster wie
    die Jahresübersicht der Zeiterfassung (jobload/overview.blade.php).
--}}
<div class="overflow-auto">
    <table class="min-w-full border-collapse text-sm">
        <thead class="text-xs text-gray-500">
            <tr>
                <th class="sticky left-0 top-0 z-20 min-w-48 border-b border-r border-gray-200 bg-gray-50 px-2 py-1.5 text-left font-medium">{{ __('Name') }}</th>
                @if ($homeTenant)
                    <th class="sticky top-0 z-10 min-w-28 border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-center font-medium" title="{{ __('Heimat-Mandant') }}">
                        {{ $homeTenant->short_name ?? $homeTenant->name }}
                    </th>
                @endif
                @foreach ($otherTenants as $tenant)
                    <th class="sticky top-0 z-10 min-w-28 border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-center font-medium">
                        {{ $tenant->short_name ?? $tenant->name }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($people as $person)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <th scope="row" class="sticky left-0 z-[1] whitespace-nowrap border-r border-gray-200 bg-white px-2 py-1 text-left font-normal {{ $person->active ? 'text-gray-800' : 'text-gray-400' }}">
                        {{ $person->fullName() }}{{ ! $person->active ? ' [i]' : '' }}
                    </th>
                    @if ($homeTenant)
                        <td class="px-2 py-1 text-center">
                            @if ($person->tenant_id === $homeTenant->id)
                                <span class="font-semibold text-gray-700" title="{{ __('Heimat-Mandant') }}">•</span>
                            @endif
                        </td>
                    @endif
                    @foreach ($otherTenants as $tenant)
                        <td class="px-2 py-1 text-center">
                            @if ($person->tenant_id === $tenant->id)
                                <span class="font-semibold text-gray-700" title="{{ __('Heimat-Mandant') }}">•</span>
                            @elseif (in_array($tenant->id, $grants->get($person->id, []), true))
                                <span class="text-green-600" title="{{ __('Kundenzugriff') }}">✓</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 1 + ($homeTenant ? 1 : 0) + $otherTenants->count() }}" class="px-3 py-6 text-center text-gray-400">{{ __('Keine Personen gefunden.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
