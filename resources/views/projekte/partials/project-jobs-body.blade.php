{{--
    Slice 1 der Zeiterfassung/Ressourcenplanung-Idee (Ralf, 2026-09-27):
    welche Jobs für dieses Projekt relevant sind. Gleiches Auswahl-Muster
    wie "Jobs anpassen" in der Zeiterfassung (jobload/index.blade.php,
    dort für die eigene Person) - hier für das Projekt, geladen ins
    globale Modal "project-jobs" (siehe layouts/app.blade.php).
--}}
<form id="project-jobs-form" method="POST" action="{{ route('projekte.jobs.update', $project) }}" class="flex max-h-[75vh] flex-col">
    @csrf
    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
        <p class="mb-3 text-xs text-gray-500">{{ __('Diese Jobs können für Projekt :pn direkt gebucht werden.', ['pn' => $project->source_pn]) }}</p>

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
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'project-jobs' }))"
            class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
        >
            {{ __('Abbrechen') }}
        </button>
        @if ($availableJobs->isNotEmpty())
            <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                {{ __('Speichern') }}
            </button>
        @endif
    </div>
</form>
