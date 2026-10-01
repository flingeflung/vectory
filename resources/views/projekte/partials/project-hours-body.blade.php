{{--
    Slice 2 der Zeiterfassung/Ressourcenplanung-Idee (Ralf, 2026-09-27):
    eigene Stunden direkt am Projekt buchen. Reiter-Leiste wie project-jobs-
    body.blade.php - "Verknüpfte Jobs" nur mit project.edit (Buchen selbst
    ist frei für jeden). Formular-Aufdeckung/Autofokus per Alpine direkt
    hier (funktioniert auch in per fetch() nachgeladenen Fragmenten -
    anders als <script>, siehe Kommentar bei copyProjectJobsFromHauptprojekt
    in layouts/app.blade.php), Speichern/Löschen läuft über den globalen
    Submit-/Klick-Handler dort.
--}}
@include('projekte.partials.project-time-tracking-header')
<div class="flex gap-1 border-b border-gray-200 px-4 pt-2">
    {{-- Ralf, 2026-09-28: "Verknüpfte Jobs" jetzt IMMER sichtbar (auch nur lesend
         ohne project.jobload.manage, siehe project-jobs-body.blade.php) - bisher
         war der Reiter ohne project.edit komplett verborgen. --}}
    <button
        type="button"
        onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'jobs')"
        class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
    >
        {{ __('Verknüpfte Jobs') }}
    </button>
    @if ($showAufteilungTab)
        <button
            type="button"
            onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'aufteilung')"
            class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
        >
            {{ __('Prozentuale Aufteilung') }}
        </button>
    @endif
    <span class="-mb-px border-b-2 border-indigo-500 px-3 py-1.5 text-xs font-medium text-gray-900">{{ __('Buchungen') }}</span>
</div>

<div class="px-4 py-3 text-sm">
    {{--
        Bestehende Buchungen IMMER zeigen, unabhängig von projectHasJobs/bookableJobs
        unten (Ralf-Fund, 2026-09-27): eine am Hauptprojekt ausgelöste Aufteilung
        schreibt Zeilen in die Unterprojekte, auch wenn dort selbst gar keine (für
        diese Person buchbaren) Jobs verknüpft sind - genau dieser Fall blendete die
        Liste vorher komplett aus und liess eine echt gespeicherte Buchung wie
        verloren wirken ("die Buchung wird als gespeichert angezeigt, ist sie aber
        nicht sichtbar").
    --}}
    @if ($entries->isNotEmpty())
        <div class="mb-3 space-y-1">
            @foreach ($entries as $entry)
                <div class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-gray-50">
                    <span class="min-w-0 flex-1 truncate">
                        <span class="text-gray-400">{{ \Illuminate\Support\Carbon::parse($entry->work_date)->format('d.m.Y') }}</span>
                        · {{ $entry->code ? $entry->code.' – ' : '' }}{{ $entry->name }}
                    </span>
                    <span class="shrink-0 font-medium tabular-nums">{{ number_format((float) $entry->hours, 2, ',', '.') }} h</span>
                    <button
                        type="button"
                        onclick="window.deleteProjectHourEntry({{ $project->id }}, {{ $entry->id }})"
                        class="shrink-0 text-gray-300 hover:text-red-600"
                        title="{{ __('Buchung löschen') }}"
                        aria-label="{{ __('Buchung löschen') }}"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    @if (! $projectHasJobs)
        <p class="text-gray-500">
            {{ __('Für dieses Projekt sind noch keine Jobs verknüpft.') }}
            @if ($canManageJobload)
                {{ __('Über den Tab „Verknüpfte Jobs" lässt sich das festlegen.') }}
            @else
                {{ __('Bitte jemanden mit der entsprechenden Berechtigung bitten, das nachzutragen.') }}
            @endif
        </p>
    @elseif ($bookableJobs->isEmpty())
        {{-- Ralf-Korrektur, 2026-09-28: bookableJobs() vergleicht nicht mehr mit dem
             eigenen Job-Anzeigefilter (siehe ProjectHourController-Docblock) - dieser
             Zweig greift jetzt nur noch, wenn die verknüpften Jobs allesamt inaktiv sind. --}}
        <p class="text-gray-500">{{ __('Die für dieses Projekt verknüpften Jobs sind alle inaktiv - buchen können Sie deshalb hier nicht.') }}</p>
    @else
        @if ($entries->isEmpty())
            <p class="mb-2 text-gray-400">{{ __('Noch keine eigenen Buchungen an diesem Projekt.') }}</p>
        @endif

        {{-- Neu-Formular hinter Trigger versteckt (Konvention), Auswahlfeld bekommt beim Aufdecken den Fokus. --}}
        <div
            x-data="{
                adding: false,
                splits: {{ Illuminate\Support\Js::from($splits ?? []) }},
                existingBookings: {{ Illuminate\Support\Js::from($existingBookings ?? []) }},
                previewHours: 0,
                selectedJobTypeId: null,
                selectedDate: null,

                // Dieselbe Größter-Rest-Logik wie splitAndBook() serverseitig (Ralf, 2026-09-27:
                // 'die genaue Verteilung der Zeit auf die UP anhand der %-Aufteilung, bevor ich
                // speichere') - rein clientseitige Vorschau, das eigentliche Buchen rechnet
                // serverseitig nochmal exakt genauso.
                breakdown() {
                    if (! this.splits.length || ! this.previewHours) { return []; }
                    const total = Math.round(this.previewHours * 100);
                    const shares = {};
                    const remainders = {};
                    let assigned = 0;
                    this.splits.forEach((s) => {
                        const exact = total * s.percentage / 100;
                        const floor = Math.floor(exact + 1e-9);
                        shares[s.id] = floor;
                        remainders[s.id] = exact - floor;
                        assigned += floor;
                    });
                    let leftover = total - assigned;
                    if (leftover > 0) {
                        const order = Object.keys(remainders).sort((a, b) => remainders[b] - remainders[a]);
                        for (let i = 0; i < leftover; i++) { shares[order[i]]++; }
                    }
                    return this.splits
                        .map((s) => ({ label: s.label, hours: shares[s.id] / 100 }))
                        .filter((s) => s.hours > 0);
                },

                // Ralf, 2026-09-27: 'sollte man den Nutzer drauf hinweisen, dass es schon
                // eine Buchung für diesen Tag gibt' - eine zweite Buchung auf denselben
                // Job/Tag ERSETZT die erste (wie in der klassischen Zeiterfassung), statt
                // sich draufzuaddieren. Nicht blockierend, nur ein Hinweis.
                alreadyBooked() {
                    return this.existingBookings.some((b) => b.job_type_id == this.selectedJobTypeId && b.work_date === this.selectedDate);
                },
            }"
            x-init="selectedJobTypeId = $refs.projectHourJobSelect.value; selectedDate = $refs.projectHourDateInput.value"
        >
            <button
                type="button"
                x-show="! adding"
                @click="adding = true; $nextTick(() => $refs.projectHourJobSelect.focus())"
                class="inline-flex items-center rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
            >
                {{ __('+ Stunden buchen') }}
            </button>

            <form
                id="project-hours-form"
                data-project-id="{{ $project->id }}"
                x-show="adding"
                x-cloak
                method="POST"
                action="{{ route('projekte.stunden.store', $project) }}"
                class="flex flex-col gap-2 rounded-md border border-gray-200 bg-gray-50 p-2"
            >
                @csrf
                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Job') }}</label>
                        <select x-ref="projectHourJobSelect" name="job_type_id" required @change="selectedJobTypeId = $event.target.value" class="mt-0.5 rounded border-gray-300 text-sm">
                            @foreach ($bookableJobs->groupBy(fn ($job) => $job->group_name ?? __('Ohne Gruppe')) as $groupName => $groupJobs)
                                <optgroup label="{{ $groupName }}">
                                    @foreach ($groupJobs as $job)
                                        <option value="{{ $job->id }}">{{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Datum') }}</label>
                        <input
                            type="date"
                            name="work_date"
                            x-ref="projectHourDateInput"
                            value="{{ now()->toDateString() }}"
                            required
                            @change="selectedDate = $event.target.value"
                            class="mt-0.5 rounded border-gray-300 text-sm"
                        >
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Stunden') }}</label>
                        <input
                            type="number"
                            name="hours"
                            step="0.25"
                            min="0.25"
                            max="24"
                            required
                            @input="previewHours = parseFloat($event.target.value) || 0"
                            class="mt-0.5 w-20 rounded border-gray-300 text-sm"
                        >
                    </div>
                    {{-- Ralf, 2026-09-27: Bestätigungs-Button einheitlich "Speichern" (siehe CLAUDE.md-
                         Konvention) - "Buchen" stand im Widerspruch zum Hinweistext direkt darunter,
                         der schon "Speichern ersetzt sie" sagte. --}}
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Speichern') }}
                    </button>
                    <button type="button" @click="adding = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        {{ __('Abbrechen') }}
                    </button>
                </div>

                <div x-show="alreadyBooked()" x-cloak class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs text-amber-700">
                    {{ __('Für diesen Job gibt es an diesem Tag bereits eine Buchung - Speichern ersetzt sie, statt die Stunden zu addieren.') }}
                </div>

                {{-- Nur am Hauptprojekt mit konfigurierter Aufteilung relevant - sonst bleibt splits leer. --}}
                <div x-show="breakdown().length" x-cloak class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-gray-200 pt-2 text-xs text-gray-500">
                    <span class="font-medium text-gray-600">{{ __('Wird verteilt auf:') }}</span>
                    <template x-for="b in breakdown()" :key="b.label">
                        <span x-text="b.label + ': ' + b.hours.toFixed(2).replace('.', ',') + ' h'"></span>
                    </template>
                </div>
            </form>
        </div>
    @endif
</div>
