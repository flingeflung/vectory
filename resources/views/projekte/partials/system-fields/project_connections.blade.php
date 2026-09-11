{{--
    Ralf, 2026-09-11: "Projektverknüpfungen" (Vietto-Vorbild: projektverbindungen).
    Bewusst NICHT im großen Projekt-Formular verschachtelt (Anlegen läuft
    über ein eigenständiges Modal, siehe layouts/app.blade.php) - ein
    <form> im <form> reißt sonst im Browser Felder ins äußere Formular
    mit rein (gleiche Anmerkung wie bei den Mail-Vorlagen).
--}}
<div
    x-data="{
        removeConnection(connectionId, projectId) {
            window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Verknüpfung entfernen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Diese Verknüpfung wirklich entfernen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Entfernen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            }).then((ok) => {
                if (! ok) return;
                fetch(`/projekte/${projectId}/verknuepfungen/${connectionId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                }).then(() => window.refreshUnderlyingProject(projectId));
            });
        },
    }"
>
    <div class="flex items-center gap-2">
        <label class="text-xs text-gray-500">{{ __('Projektverknüpfungen') }}</label>
        <button type="button" onclick="window.openProjectConnectionAdd({{ $project->id }})" class="{{ $secondaryBtn }}">
            {{ __('Verknüpfen') }}
        </button>
    </div>

    @php $connections = $project->connections(); @endphp
    @if ($connections->isEmpty())
        <div class="mt-0.5 text-gray-400">&ndash; {{ __('Keine Verknüpfungen') }} &ndash;</div>
    @else
        <div class="mt-1 space-y-1">
            @foreach ($connections as $entry)
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-gray-500">{{ $entry->label }}:</span>
                        <a
                            href="#"
                            onclick="window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $entry->otherProject->id }} } })); return false;"
                            class="font-medium text-indigo-600 hover:underline"
                        >{{ $entry->otherProject->source_pn }} &ndash; {{ $entry->otherProject->title }}</a>
                    </div>
                    <button
                        type="button"
                        @click="removeConnection({{ $entry->connection->id }}, {{ $project->id }})"
                        class="shrink-0 text-gray-400 hover:text-red-600"
                        title="{{ __('Verknüpfung entfernen') }}"
                    >&times;</button>
                </div>
            @endforeach
        </div>
    @endif
</div>
