<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Meine Jobs') }}</h2>
    </x-slot>

    @php
        $weekValue = sprintf('%04d-W%02d', $week->isoWeekYear(), $week->isoWeek());
        $selectedIds = $jobs->pluck('id')->all();
        $total = 0;
        // Slice 3 (2026-09-28): "Summe" je Tag ist jetzt Projekt-Stunden + eigene Ergaenzung -
        // beide getrennt fuer die Fusszeile mitgefuehrt, damit sie dort ebenfalls je Spalte
        // (Projekt / Eigene / Tag) statt nur als ein Gesamtwert erscheinen koennen.
        $dayManualTotals = [];
        $dayProjectTotals = [];
        $dayTotals = [];
        foreach ($days as $day) {
            $dayManualTotals[$day->toDateString()] = $jobs->sum(fn ($job) => (float) ($hours->get($job->id)?->get($day->toDateString()) ?? 0));
            $dayProjectTotals[$day->toDateString()] = $jobs->sum(fn ($job) => (float) ($projectHours->get($job->id)?->get($day->toDateString()) ?? 0));
            $dayTotals[$day->toDateString()] = $dayManualTotals[$day->toDateString()] + $dayProjectTotals[$day->toDateString()];
        }
        $hasProjectHours = $jobs->contains(fn ($job) => $projectHours->get($job->id)?->isNotEmpty());
    @endphp
    <div class="p-3 sm:p-4">
        <div class="mx-auto max-w-none space-y-2" x-data="{
            showWeekends: @js($showWeekends),
            savingWeekends: false,
            async saveWeekendPreference(event) {
                const requested = event.target.checked;
                this.showWeekends = requested;
                this.savingWeekends = true;
                try {
                    const response = await fetch({{ \Illuminate\Support\Js::from(route('jobload.weekend')) }}, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }},
                        },
                        body: JSON.stringify({ show_weekends: requested }),
                    });
                    if (!response.ok) throw new Error('save failed');
                } catch (error) {
                    this.showWeekends = !requested;
                    await window.notifyDialog({{ \Illuminate\Support\Js::from(__('Einstellung konnte nicht gespeichert werden.')) }});
                } finally {
                    this.savingWeekends = false;
                }
            },
        }">
            @if (session('status'))
                <x-flash-message role="status" class="px-4 py-3 text-sm">{{ session('status') }}</x-flash-message>
            @endif
            @if ($errors->any())
                <div role="alert" class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2">
                <a href="{{ route('jobload', ['week' => sprintf('%04d-W%02d', $week->subWeek()->isoWeekYear(), $week->subWeek()->isoWeek())]) }}" onclick="return window.navigateOrConfirm(event)" class="rounded border border-gray-300 px-2 py-1 text-sm hover:bg-gray-50" aria-label="{{ __('Vorherige Woche') }}">←</a>
                <form method="GET" action="{{ route('jobload') }}" class="flex items-center">
                    <label class="flex items-center gap-2 text-sm text-gray-700">{{ __('KW') }}
                        <input type="week" name="week" value="{{ $weekValue }}" onchange="window.jobloadChangeWeek(this)" class="rounded-md border-gray-300 py-1 text-sm">
                    </label>
                </form>
                <a href="{{ route('jobload', ['week' => sprintf('%04d-W%02d', $week->addWeek()->isoWeekYear(), $week->addWeek()->isoWeek())]) }}" onclick="return window.navigateOrConfirm(event)" class="rounded border border-gray-300 px-2 py-1 text-sm hover:bg-gray-50" aria-label="{{ __('Nächste Woche') }}">→</a>
                <span class="text-xs text-gray-600">{{ $week->format('d.m.Y') }} – {{ $week->addDays(6)->format('d.m.Y') }}</span>
                <label class="ml-5 flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" x-model="showWeekends" @change="saveWeekendPreference($event)" :disabled="savingWeekends" class="rounded border-gray-300">
                    {{ __('Sa/So anzeigen') }}
                </label>
                <button type="button" x-data @click="$dispatch('open-modal', 'jobload-jobs')" class="ml-auto rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Job-Anzeigefilter') }}</button>
            </div>

            <form id="jobload-hours-form" method="POST" action="{{ route('jobload.hours') }}" x-data="{ dirty: false }" x-init="window.adminPageIsDirty = () => dirty" @input="dirty = window.formIsDirty($el)" @change="dirty = window.formIsDirty($el)" @submit="dirty = false" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @csrf
                <input type="hidden" name="week" value="{{ $weekValue }}">
                <div id="jobload-grid-error" role="alert" class="hidden px-3 py-1 text-xs text-red-700"></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th rowspan="2" scope="col" class="min-w-36 border-b border-gray-200 px-2 py-1 text-left font-medium">{{ __('Job') }}</th>
                                @foreach ($days as $day)
                                    <th scope="colgroup" colspan="3" @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="border-b border-l border-gray-200 px-0.5 py-1 text-center font-medium {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">{{ ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][$loop->index] }} {{ $day->format('d.m.') }}</th>
                                @endforeach
                                <th rowspan="2" scope="col" class="border-b border-gray-200 px-2 py-1 text-right font-medium">{{ __('Summe') }}</th>
                            </tr>
                            <tr class="border-b border-gray-200 text-[9px] font-normal uppercase tracking-wide text-gray-400">
                                @foreach ($days as $day)
                                    <th scope="col" @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="w-10 border-l border-gray-200 px-0.5 py-0.5 text-center font-normal {{ $day->isWeekend() ? 'bg-amber-50' : '' }}" title="{{ __('Projekt-Stunden - schreibgeschützt, kommen aus dem Projekt') }}">{{ __('Proj.') }}</th>
                                    <th scope="col" @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="w-12 px-0.5 py-0.5 text-center font-normal {{ $day->isWeekend() ? 'bg-amber-50' : '' }}" title="{{ __('Eigene Ergänzung ohne Projektbezug') }}">{{ __('Eig.') }}</th>
                                    <th scope="col" @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="w-10 px-0.5 py-0.5 text-center font-normal {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">{{ __('Tag') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jobs->groupBy(fn ($job) => $job->group_name ?? __('Ohne Gruppe')) as $groupName => $groupJobs)
                                <tr class="border-b border-gray-100 bg-gray-50/70">
                                    <th colspan="23" scope="colgroup" class="px-2 py-0.5 text-left text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $groupName }}</th>
                                </tr>
                                @foreach ($groupJobs as $job)
                                    @php
                                        $jobTotal = 0;
                                    @endphp
                                    <tr class="border-b border-gray-100">
                                        <th scope="row" class="whitespace-nowrap px-2 py-0.5 text-left font-medium text-gray-800">
                                            {{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}
                                        </th>
                                        @foreach ($days as $day)
                                            @php
                                                $value = $hours->get($job->id)?->get($day->toDateString());
                                                $projectValue = (float) ($projectHours->get($job->id)?->get($day->toDateString()) ?? 0);
                                                $daySum = (float) ($value ?? 0) + $projectValue;
                                                $jobTotal += $daySum;
                                                $legacyValue = $value !== null && (int) round((float) $value * 100) % $stepHundredths !== 0;
                                                // Slice 3 (2026-09-28): Farbe auf der Tagessumme - amber wenn Projekt-Stunden
                                                // UND eigene Ergaenzung zusammenkommen (eigenes Risiko der manuellen
                                                // Ergaenzung), indigo wenn der Tag rein projektbezogen ist. Rein manuell
                                                // (wie bisher) bleibt neutral, siehe Legende unter der Tabelle.
                                                $daySumColor = $projectValue > 0 && (float) ($value ?? 0) > 0
                                                    ? 'text-amber-700'
                                                    : ($projectValue > 0 ? 'text-indigo-700' : 'text-gray-800');
                                            @endphp
                                            <td @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="border-l border-gray-100 px-0.5 py-0.5 text-center tabular-nums text-gray-400 {{ $day->isWeekend() ? 'bg-amber-50' : '' }}" title="{{ __('Projekt-Stunden - schreibgeschützt, kommen aus dem Projekt') }}">
                                                {{ $projectValue > 0 ? number_format($projectValue, $hourDecimals, ',', '.') : '–' }}
                                            </td>
                                            <td @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="px-0.5 py-0.5 text-center {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">
                                                <input type="number" name="hours[{{ $job->id }}][{{ $day->toDateString() }}]" value="{{ $value === null ? '' : number_format((float) $value, $legacyValue ? 2 : $hourDecimals, '.', '') }}" min="0" max="24" step="{{ $legacyValue ? 'any' : [60 => '1', 30 => '0.5', 15 => '0.25'][$timeGrid] }}" data-original-value="{{ $value === null ? '' : number_format((float) $value, 2, '.', '') }}" @if ($legacyValue) data-legacy="1" @endif inputmode="decimal" oninput="window.validateJobloadGrid(this)" onkeydown="window.stepLegacyJobloadHour(event, this)" aria-label="{{ $job->name }} {{ $day->format('d.m.Y') }}" class="w-12 rounded border-gray-300 px-0.5 py-0.5 text-right text-xs">
                                            </td>
                                            <td @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="px-0.5 py-0.5 text-center font-medium tabular-nums {{ $daySumColor }} {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">
                                                {{ $daySum > 0 ? number_format($daySum, $hourDecimals, ',', '.') : '–' }}
                                            </td>
                                        @endforeach
                                        @php
                                            $total += $jobTotal;
                                        @endphp
                                        <td class="px-2 py-0.5 text-right font-medium tabular-nums">{{ number_format($jobTotal, $hourDecimals, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr><td colspan="23" class="px-3 py-6 text-center text-gray-500">{{ __('Noch keine Jobs ausgewählt. Über „Job-Anzeigefilter“ können Sie welche einblenden.') }}</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 font-medium text-gray-800">
                            <tr>
                                <th class="px-2 py-1 text-left">{{ __('Summe') }}</th>
                                @foreach ($days as $day)
                                    <td @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="border-l border-gray-200 px-0.5 py-1 text-center tabular-nums text-gray-500 {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">{{ $dayProjectTotals[$day->toDateString()] > 0 ? number_format($dayProjectTotals[$day->toDateString()], $hourDecimals, ',', '.') : '–' }}</td>
                                    <td @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="px-0.5 py-1 text-center tabular-nums text-gray-500 {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">{{ $dayManualTotals[$day->toDateString()] > 0 ? number_format($dayManualTotals[$day->toDateString()], $hourDecimals, ',', '.') : '–' }}</td>
                                    <td @if ($day->isWeekend()) x-show="showWeekends" x-cloak @endif class="px-0.5 py-1 text-center tabular-nums {{ $day->isWeekend() ? 'bg-amber-50' : '' }}">{{ number_format($dayTotals[$day->toDateString()], $hourDecimals, ',', '.') }}</td>
                                @endforeach
                                <td class="px-2 py-1 text-right tabular-nums">{{ number_format($total, $hourDecimals, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($hasProjectHours)
                    {{-- Ralf, 2026-09-28: Legenden-Punkte müssen exakt denselben Farbton wie die
                         Textfarbe der Tagessumme in der Tabelle treffen (bg-indigo-700/bg-amber-700,
                         nicht die hellere -500-Stufe) - sonst wirken sie wie andere Farben. --}}
                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-2 py-1 text-[10px] text-gray-400">
                        <span>{{ __('Erläuterung zu den Summenspalten:') }}</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-1.5 w-1.5 rounded-full bg-indigo-700"></span>{{ __('direkt auf ein Projekt gebuchte Std.') }}</span>
                        <span class="flex items-center gap-1"><span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-700"></span>{{ __('Projekt-Stunden + eigene Ergänzung') }}</span>
                    </div>
                @endif
                @if ($jobs->isNotEmpty())
                    <div x-show="dirty" x-cloak class="flex justify-end border-t border-gray-200 px-3 py-2">
                        <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <x-modal name="jobload-jobs" max-width="lg" :draggable="true">
        <form method="POST" action="{{ route('jobload.jobs') }}" onsubmit="return window.jobloadConfirmJobs(event)" class="flex max-h-[75vh] flex-col">
            @csrf
            <input type="hidden" name="week" value="{{ $weekValue }}">
            <div class="flex shrink-0 items-center justify-between border-b border-gray-200 bg-gray-100 px-4 py-2" data-drag-handle title="{{ __('Ziehen zum Verschieben') }}">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('Angezeigte Jobs') }}</h2>
                <button type="button" @click="$dispatch('close-modal', 'jobload-jobs')" class="text-gray-500 hover:text-gray-700" aria-label="{{ __('Schließen') }}">×</button>
            </div>
            <div class="min-h-0 overflow-y-auto p-3 text-sm">
                {{-- Ralf, 2026-09-28: "Anpassen" klang nach "darf/darf nicht" - ist aber nur
                     eine Anzeige-Auswahl fürs eigene Wochenraster. Wer Zugriff auf einen
                     Kunden hat, kann trotzdem auf jeden dort verknüpften Job buchen (siehe
                     ProjectHourController), unabhängig von dieser Auswahl. --}}
                <p class="mb-1.5 text-xs text-gray-500">{{ __('Hier legen Sie fest, welche Jobs in Ihrer eigenen Übersicht erscheinen sollen.') }}</p>
                @forelse ($availableJobs->groupBy(fn ($job) => $job->group_name ?? __('Ohne Gruppe')) as $groupName => $groupJobs)
                    <div class="pt-1.5 text-xs font-semibold text-gray-700">{{ $groupName }}</div>
                    @foreach ($groupJobs as $job)
                        <label class="flex items-center gap-2 rounded px-1.5 py-0.5 text-xs hover:bg-gray-50">
                            <input type="checkbox" name="jobs[]" value="{{ $job->id }}" @checked(in_array($job->id, $selectedIds)) class="rounded border-gray-300">
                            <span>{{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}</span>
                        </label>
                    @endforeach
                @empty
                    <p class="text-gray-500">{{ __('Es sind noch keine Jobs verfügbar.') }}</p>
                @endforelse
            </div>
            <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 px-4 py-3">
                <button type="button" @click="$dispatch('close-modal', 'jobload-jobs')" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                @if ($availableJobs->isNotEmpty())
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover">{{ __('Speichern') }}</button>
                @endif
            </div>
        </form>
    </x-modal>
    <script>
        window.validateJobloadGrid = function (input) {
            const stepHundredths = {{ [60 => 100, 30 => 50, 15 => 25][$timeGrid] }};
            const value = input.value;
            const hundredths = Number(value) * 100;
            const unchanged = input.dataset.originalValue !== '' && Math.abs(hundredths - Number(input.dataset.originalValue) * 100) < 0.0001;
            const invalid = input.validity.badInput || (value !== '' && !unchanged &&
                (!Number.isFinite(hundredths) || Math.abs(hundredths - Math.round(hundredths)) > 0.0001 ||
                    Math.round(hundredths) % stepHundredths !== 0));
            const message = {{ \Illuminate\Support\Js::from(__('Bitte Werte in :step-Stunden-Schritten eingeben.', ['step' => [60 => '1', 30 => '0,5', 15 => '0,25'][$timeGrid]])) }};
            input.setCustomValidity(invalid ? message : '');
            input.classList.toggle('border-red-500', invalid);
            input.classList.toggle('ring-1', invalid);
            input.classList.toggle('ring-red-200', invalid);
            input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
            const error = document.getElementById('jobload-grid-error');
            const firstInvalid = document.querySelector('#jobload-hours-form input[aria-invalid="true"]');
            error.textContent = firstInvalid ? message + ' ' + firstInvalid.getAttribute('aria-label') : '';
            error.classList.toggle('hidden', !firstInvalid);
        };

        window.stepLegacyJobloadHour = function (event, input) {
            if (!input.dataset.legacy || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
            event.preventDefault();
            const step = {{ $timeGrid / 60 }};
            const current = Number(input.value) || 0;
            const next = event.key === 'ArrowUp'
                ? (Math.floor(current / step) + 1) * step
                : (Math.ceil(current / step) - 1) * step;
            input.value = Math.max(0, Math.min(24, next)).toFixed({{ $hourDecimals }});
            input.dispatchEvent(new Event('input', { bubbles: true }));
        };

        window.jobloadChangeWeek = async function (input) {
            if (await window.confirmDiscardIfDirty('adminPageIsDirty')) {
                window.adminPageIsDirty = () => false;
                input.form.submit();
            } else {
                input.value = {{ \Illuminate\Support\Js::from($weekValue) }};
            }
        };

        window.jobloadConfirmJobs = function (event) {
            if (!window.adminPageIsDirty()) {
                return true;
            }
            event.preventDefault();
            const form = event.currentTarget;
            window.confirmDiscardIfDirty('adminPageIsDirty').then((discard) => {
                if (discard) {
                    window.adminPageIsDirty = () => false;
                    form.submit();
                }
            });
            return false;
        };

        window.addEventListener('beforeunload', (event) => {
            if (window.adminPageIsDirty()) {
                event.preventDefault();
                event.returnValue = '';
            }
        });
    </script>
</x-app-layout>
