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
    <form method="GET" action="{{ route('planung.stunden') }}" class="mb-3 flex shrink-0 items-center gap-2 text-sm">
        <label class="flex items-center gap-2 text-gray-700">{{ __('Jahr') }}
            <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-sm">
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </label>
    </form>
    {{-- Ralf, 2026-09-29: Abteilungsfilter ersatzlos entfernt (funktionierte
         nicht zuverlässig für alle Szenarien) - welche Personen hier
         auftauchen, wird jetzt direkt je Person in den Personendetails
         gepflegt (Checkbox "Ressourcenplanung"). --}}

    <div id="planning-stunden-content" class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
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
                    <th class="px-3 py-2"></th>
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
                        <td class="px-3 py-1.5">
                            {{-- Ralf, 2026-09-29: direkt von hier aus die Personendetails
                                 öffnen können, statt erst dorthin navigieren zu müssen -
                                 gleiches globale Personen-Overlay wie überall sonst im
                                 Tool, die Seite hier aktualisiert sich nach dem
                                 Schließen automatisch (siehe Script unten). --}}
                            <x-edit-icon-button
                                :title="__('Personendetails bearbeiten')"
                                onclick="window.dispatchEvent(new CustomEvent('open-person', { detail: { id: {{ $row['personId'] }} } }))"
                            />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ __('Für :year sind keine Personen sichtbar - entweder ist bei niemandem "Ressourcenplanung" angehakt, oder es fehlen gültige Wochenstunden-Daten für dieses Jahr.', ['year' => $year]) }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-gray-50 font-semibold text-gray-800">
                    <tr>
                        <td class="px-3 py-2" colspan="5">{{ __('Summe') }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($total) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <script>
        // Ralf, 2026-09-29: nach dem Bearbeiten einer Person im globalen
        // Personen-Overlay (siehe oben) die Tabelle hier automatisch
        // nachziehen, ohne die ganze Seite neu zu laden - z.B. wenn die
        // Wochenstunden gerade korrigiert wurden.
        window.addEventListener('close-modal', async (event) => {
            if (event.detail !== 'person-overlay') {
                return;
            }
            const container = document.getElementById('planning-stunden-content');
            if (!container) {
                return;
            }
            const html = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } }).then((r) => r.text());
            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('planning-stunden-content');
            if (fresh) {
                container.outerHTML = fresh.outerHTML;
            }
        });
    </script>
</x-planning-layout>
