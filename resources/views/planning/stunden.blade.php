{{--
    Planung > Stunden (Ralf, 2026-09-28) - Jahresstunden-Kapazität je Person,
    Grundlage der personellen Ressourcenplanung. Berechnung aus Wochenstunden-
    und Urlaubstage-Historie (siehe PlanningController::stunden()) - beide
    können sich unterjährig ändern, die Jahresstunden werden deshalb
    abschnittsweise berechnet statt mit einem einzelnen WoSt-Wert
    hochgerechnet.
--}}
<x-planning-layout>
    @php($fmt = fn ($value) => number_format($value, 1, ',', '.'))
    <form method="GET" action="{{ route('planung.stunden') }}" class="mb-3 flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 text-sm">
        <input type="hidden" name="departments_submitted" value="1">
        <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
            <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </label>
        {{-- Ralf, 2026-09-28: Abteilungsfilter statt eines Dienstleister-Flags -
             funktioniert auch ohne Mandantenfähigkeit, wo alle Personen eigene
             Mitarbeiter sind ("man markiert die Abteilungen, die man
             ausgewertet haben möchte"). --}}
        <fieldset class="flex flex-wrap items-center gap-x-3 gap-y-1 text-gray-600">
            <legend class="sr-only">{{ __('Abteilungen') }}</legend>
            @foreach ($departments as $department)
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="departments[]" value="{{ $department->id }}" onchange="this.form.submit()" @checked(in_array((string) $department->id, $selectedDepartments, true)) class="rounded border-gray-300">
                    {{ $department->name }}
                </label>
            @endforeach
            <label class="flex items-center gap-1.5 text-gray-400">
                <input type="checkbox" name="departments[]" value="none" onchange="this.form.submit()" @checked(in_array('none', $selectedDepartments, true)) class="rounded border-gray-300">
                {{ __('– nicht zugewiesen –') }}
            </label>
        </fieldset>
    </form>

    <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
                <tr class="border-b border-gray-200">
                    <th class="px-3 py-2 text-left font-medium">{{ __('Vorname') }}</th>
                    <th class="px-3 py-2 text-left font-medium">{{ __('Nachname') }}</th>
                    <th class="px-3 py-2 text-right font-medium">{{ __('WoStd') }}</th>
                    <th class="px-3 py-2 text-right font-medium" title="{{ __('Reine Wochentage (Mo-Fr) des Jahres - ohne Feiertage oder Krankheitstage abzuziehen.') }}">
                        {{ __('Arbeitstage') }}
                    </th>
                    <th class="px-3 py-2 text-right font-medium">{{ __('Urlaub (Std)') }}</th>
                    <th class="px-3 py-2 text-right font-medium">{{ __('Jahresstd.') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-3 py-1.5">{{ $row['firstName'] }}</td>
                        <td class="px-3 py-1.5">{{ $row['lastName'] }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ $fmt($row['wost']) }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $totalWorkdays }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ $fmt($row['vacationHours']) }}</td>
                        <td class="px-3 py-1.5 text-right font-medium tabular-nums">{{ $fmt($row['jahresstd']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('Für :year sind keine Personen mit gültigen Wochenstunden-Daten sichtbar.', ['year' => $year]) }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr>
                        <td class="px-3 py-2" colspan="5">{{ __('Summe') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($total) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-planning-layout>
