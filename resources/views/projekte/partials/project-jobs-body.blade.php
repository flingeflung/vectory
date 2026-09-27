{{--
    Slice 1 der Zeiterfassung/Ressourcenplanung-Idee (Ralf, 2026-09-27):
    welche Jobs für dieses Projekt relevant sind. Gleiches Auswahl-Muster
    wie "Jobs anpassen" in der Zeiterfassung (jobload/index.blade.php,
    dort für die eigene Person) - hier für das Projekt, geladen ins
    globale Modal "project-time-tracking" (siehe layouts/app.blade.php).

    Reiter-Leiste analog zur Verzeichnis-Vorschau (Arbeitsverzeichnis/
    Gesperrtes Verzeichnis, 2026-09-26). "Buchungen" (Slice 2, 2026-09-27)
    ist für jeden da (Buchen ist keine Projekt-Bearbeitung) - dieser Reiter
    hier nur, solange man project.edit hat (siehe Controller).
--}}
@include('projekte.partials.project-time-tracking-header')
<div class="flex gap-1 border-b border-gray-200 px-4 pt-2">
    <span class="-mb-px border-b-2 border-indigo-500 px-3 py-1.5 text-xs font-medium text-gray-900">{{ __('Verknüpfte Jobs') }}</span>
    @if ($showAufteilungTab)
        <button
            type="button"
            onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'aufteilung')"
            class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
        >
            {{ __('Prozentuale Aufteilung') }}
        </button>
    @endif
    <button
        type="button"
        onclick="window.switchProjectTimeTrackingTab({{ $project->id }}, 'buchungen')"
        class="-mb-px border-b-2 border-transparent px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
    >
        {{ __('Buchungen') }}
    </button>
</div>

{{--
    h-full statt max-h-[75vh] (Ralf-Fund, 2026-09-27: "Speichern-Button ist nur per
    Scroll vollständig sichtbar"): dieses Formular sitzt bereits in einer eigenen
    scrollenden Box (#project-jobs-body in layouts/app.blade.php) - eine zweite,
    unabhängig geschätzte Höhe (75vh) darin führte dazu, dass bei vielen Jobs die
    ÄUSSERE Box gescrollt hat statt der inneren, wodurch der per shrink-0 fixierte
    Speichern-Footer nicht mehr am sichtbaren unteren Rand klebte. h-full übernimmt
    stattdessen exakt die vom Elternelement tatsächlich zugeteilte Höhe.
--}}
<form id="project-jobs-form" x-data="{ dirty: false }" @input="dirty = window.formIsDirty($el)" method="POST" action="{{ route('projekte.jobs.update', $project) }}" class="flex h-full min-h-0 flex-col">
    @csrf
    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
        <div class="mb-3 flex items-start justify-between gap-3">
            <p class="text-xs text-gray-500">{{ __('Auf diese Jobs kann von Projekt :pn direkt gebucht werden.', ['pn' => $project->source_pn]) }}</p>

            @if ($project->verbund_rolle === 2)
                {{--
                    Ralf, 2026-09-27: für Unterprojekte, die lieber eigenständig statt über die
                    prozentuale Umlage des Hauptprojekts buchen wollen - übernimmt die KOMPLETTE
                    Auswahl des Hauptprojekts (ersetzt die eigene, noch nicht gespeichert - erst
                    "Speichern" übernimmt es wirklich, siehe Override/Fallback-Konvention).
                --}}
                <button
                    type="button"
                    {{ empty($hauptprojektJobIds) ? 'disabled' : '' }}
                    data-hauptprojekt-job-ids="{{ implode(',', $hauptprojektJobIds) }}"
                    onclick="window.copyProjectJobsFromHauptprojekt(this)"
                    title="{{ empty($hauptprojektJobIds) ? __('Das Hauptprojekt :pn hat noch keine Jobs verknüpft.', ['pn' => $hauptprojektTitle]) : __('Übernimmt die Job-Auswahl von :pn.', ['pn' => $hauptprojektTitle]) }}"
                    class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ __('Vom Hauptprojekt kopieren') }}
                </button>
            @endif
        </div>

        @forelse ($availableJobs->groupBy(fn ($job) => $job->group_name ?? __('Ohne Gruppe')) as $groupName => $groupJobs)
            <div class="pt-2 text-sm font-semibold text-gray-700">{{ $groupName }}</div>
            @foreach ($groupJobs as $job)
                <label class="flex items-center gap-3 rounded px-2 py-1 hover:bg-gray-50">
                    <input type="checkbox" name="jobs[]" value="{{ $job->id }}" @checked(in_array($job->id, $selectedIds)) class="rounded border-gray-300">
                    <span>{{ $job->code ? $job->code.' – ' : '' }}{{ $job->name }}</span>
                </label>
            @endforeach
        @empty
            <p class="text-gray-500">{{ __('Es sind noch keine Jobs verfügbar.') }}</p>
        @endforelse
    </div>
    <div class="flex shrink-0 justify-end gap-2 border-t border-gray-200 px-4 py-3">
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-time-tracking' }))"
            class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
        >
            {{ __('Abbrechen') }}
        </button>
        @if ($availableJobs->isNotEmpty())
            <button type="submit" x-show="dirty" x-cloak class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Speichern') }}
            </button>
        @endif
    </div>
</form>
