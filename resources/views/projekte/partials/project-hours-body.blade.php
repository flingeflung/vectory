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
<div class="flex gap-1 border-b border-gray-200 px-4 pt-2">
    @if ($canEditJobs)
        <button
            type="button"
            onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'jobs')"
            class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
        >
            {{ __('Verknüpfte Jobs') }}
        </button>
    @endif
    <span class="-mb-px border-b-2 border-indigo-500 px-3 py-1.5 text-xs font-medium text-gray-900">{{ __('Buchungen') }}</span>
</div>

<div class="px-4 py-3 text-sm">
    @if (! $projectHasJobs)
        <p class="text-gray-500">
            {{ __('Für dieses Projekt sind noch keine Jobs verknüpft.') }}
            @if ($canEditJobs)
                {{ __('Über den Reiter „Verknüpfte Jobs" lässt sich das festlegen.') }}
            @else
                {{ __('Bitte jemanden mit Bearbeitungsrecht am Projekt bitten, das nachzutragen.') }}
            @endif
        </p>
    @elseif ($bookableJobs->isEmpty())
        <p class="text-gray-500">{{ __('Keiner der für dieses Projekt verknüpften Jobs ist Ihnen selbst zugewiesen - buchen können Sie deshalb hier nicht. Die eigenen Jobs lassen sich in der Zeiterfassung anpassen.') }}</p>
    @else
        <div class="mb-3 space-y-1">
            @forelse ($entries as $entry)
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
            @empty
                <p class="text-gray-400">{{ __('Noch keine eigenen Buchungen an diesem Projekt.') }}</p>
            @endforelse
        </div>

        {{-- Neu-Formular hinter Trigger versteckt (Konvention), Auswahlfeld bekommt beim Aufdecken den Fokus. --}}
        <div x-data="{ adding: false }">
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
                class="flex flex-wrap items-end gap-2 rounded-md border border-gray-200 bg-gray-50 p-2"
            >
                @csrf
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Job') }}</label>
                    <select x-ref="projectHourJobSelect" name="job_type_id" required class="mt-0.5 rounded border-gray-300 text-sm">
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
                    <input type="date" name="work_date" value="{{ now()->toDateString() }}" required class="mt-0.5 rounded border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500">{{ __('Stunden') }}</label>
                    <input type="number" name="hours" step="0.25" min="0.25" max="24" required class="mt-0.5 w-20 rounded border-gray-300 text-sm">
                </div>
                <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                    {{ __('Buchen') }}
                </button>
                <button type="button" @click="adding = false" class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                    {{ __('Abbrechen') }}
                </button>
            </form>
        </div>
    @endif
</div>
