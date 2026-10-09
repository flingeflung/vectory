{{--
    Planung › Erinnerungen (Ralf, 2026-10-09): alle Erinnerungsmails (Mail-Timer) der Organisation, nur lesend. Daten: PlanningMailTimerController.
--}}
<x-planning-layout>
    <form method="GET" action="{{ route('planung.erinnerungen') }}" class="mb-3 flex shrink-0 flex-wrap items-center gap-4 text-sm">
        <label class="flex items-center gap-2 text-gray-700">
            <input type="checkbox" name="erledigte" value="1" @checked($showDone) onchange="this.form.submit()" class="rounded border-gray-300">
            {{ __('Auch gesendete und übersprungene zeigen') }}
        </label>
        <span class="text-xs text-gray-400">{{ trans_choice(':count Erinnerung|:count Erinnerungen', $rows->count(), ['count' => $rows->count()]) }}</span>
    </form>

    <div class="min-h-0 flex-1 overflow-auto rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-gray-50 text-xs text-gray-500">
                <tr class="border-b border-gray-200 text-left">
                    <th class="whitespace-nowrap px-3 py-2 font-medium" title="{{ __('Der Tag, an dem die Mail versendet wird (täglich um 06:30 Uhr).') }}">{{ __('Sendedatum') }}</th>
                    <th class="whitespace-nowrap px-3 py-2 font-medium">{{ __('PN') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Projekt') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Schritt') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Mail-Vorlage') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Bezug') }}</th>
                    <th class="px-3 py-2 font-medium">{{ __('Empfänger') }}</th>
                    <th class="whitespace-nowrap px-3 py-2 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    @php
                        $timer = $row['timer'];
                        $project = $row['project'];
                        $overdue = $timer->isPending() && $timer->send_date && $timer->send_date->lt(today());
                    @endphp
                    <tr class="align-top hover:bg-gray-50">
                        <td class="whitespace-nowrap px-3 py-2 font-medium {{ $overdue ? 'text-red-700' : 'text-gray-800' }}" @if ($overdue) title="{{ __('Das Sendedatum liegt in der Vergangenheit, die Mail wurde noch nicht versendet.') }}" @endif>{{ $timer->send_date ? $timer->send_date->format('d.m.Y') : __('wartet auf Termin') }}</td>
                        <td class="whitespace-nowrap px-3 py-2"><x-pn-link :project="$project" /></td>
                        <td class="px-3 py-2 text-gray-700">{{ $project->title }}</td>
                        <td class="px-3 py-2 text-gray-600">
                            {{ $timer->step?->title ?? '–' }}
                            @if ($timer->step && ! $timer->only_if_in_step)
                                <span class="block text-xs text-gray-400" title="{{ __('Wird unabhängig vom Schritt gesendet.') }}">{{ __('immer senden') }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-700">{{ $timer->mailTemplate?->name ?? '–' }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $row['rule'] }}</td>
                        <td class="px-3 py-2 text-gray-600" title="{{ __(':n Personen mit E-Mail-Adresse', ['n' => $row['recipients']]) }}">{{ implode(', ', $row['groups']) ?: '–' }} <span class="text-gray-400">({{ $row['recipients'] }})</span></td>
                        <td class="whitespace-nowrap px-3 py-2">
                            @if ($timer->sent_at)
                                <span class="text-green-800">{{ __('gesendet am :date', ['date' => $timer->sent_at->local()->format('d.m.Y')]) }}</span>
                            @elseif ($timer->skipped_at)
                                <span class="text-gray-500" title="{{ __('Das Projekt war am Sendetag nicht mehr im Schritt oder ist beendet.') }}">{{ __('übersprungen') }}</span>
                            @elseif ($timer->last_error)
                                <span class="text-red-700" title="{{ $timer->last_error }}">{{ __('Fehler') }}</span>
                            @else
                                <span class="text-gray-700">{{ __('geplant') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">{{ $showDone ? __('Es gibt noch keine Erinnerungen.') : __('Es sind keine Erinnerungen offen. Angelegt werden sie im Projekt über das Uhr-Symbol im Workflow-Schritt.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-planning-layout>
