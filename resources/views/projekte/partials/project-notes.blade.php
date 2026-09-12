@php
    $boxId = 'project-notes-'.$type.'-'.($editable ? 'edit' : 'echo').'-'.$project->id;
@endphp

{{-- Ralf, 2026-09-12 (Vietto-Vorbild "bemerkungen", intTyp 1=Bemerkung/
     2=Änderung zur Vorversion): gemeinsame Komponente für beide Felder,
     $type unterscheidet die Datenbank-Zeilen (ProjectNote::TYPE_REMARK/
     TYPE_CHANGE). Jeder Eintrag trägt Autor + Zeitpunkt und wird sofort
     gespeichert bzw. gelöscht (kein Speichern-Button, kein Freigabe-
     Workflow - Ralf: "wie eine E-Mail, die wird ja auch nicht
     freigegeben"), deshalb eigenständige Fetches statt Teil des großen
     Projekt-Formulars (gleicher Grund wie bei den Projektverknüpfungen:
     ein <form> im <form> reißt sonst Felder ins äußere Formular mit rein).

     $editable=false (Ablaufdaten-Duplikat von "Bemerkungen", siehe
     system-fields/remarks_echo.blade.php) zeigt nur die beim Laden des
     Overlays vorhandenen Einträge, ohne Neu/Löschen - wie beim bisherigen
     Duplikat ist das eine Momentaufnahme, kein Live-Abgleich mit der
     editierbaren Stammdaten-Box.

     WICHTIG für Änderungen an diesem x-data-Block: KEINE geraden
     Anführungszeichen in JS-Kommentaren dort verwenden - die beenden das
     umschließende x-data="..."-Attribut vorzeitig (siehe gleiche Anmerkung
     in connection-add-body.blade.php, dort schon zweimal passiert). --}}
<div
    @if ($editable)
        x-data="{
            adding: false,
            text: '',
            saving: false,
            async addNote() {
                if (! this.text.trim()) return;
                this.saving = true;
                try {
                    const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.notizen.store', $project)) }}, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ type: {{ \Illuminate\Support\Js::from($type) }}, text: this.text, box_id: {{ \Illuminate\Support\Js::from($boxId) }} }),
                    });
                    if (! response.ok) {
                        window.notifyDialog({{ \Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte erneut versuchen.')) }});
                        return;
                    }
                    const box = document.getElementById({{ \Illuminate\Support\Js::from($boxId) }});
                    box.querySelector('.project-notes-empty')?.remove();
                    const html = await response.text();
                    box.insertAdjacentHTML('beforeend', html);
                    this.text = '';
                    this.adding = false;
                } finally {
                    this.saving = false;
                }
            },
            deleteNote(id) {
                window.confirmDialog({{ \Illuminate\Support\Js::from(__('Eintrag wirklich löschen?')) }}).then(async (ok) => {
                    if (! ok) return;
                    const response = await fetch(`/projekte/{{ $project->id }}/notizen/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    });
                    if (response.ok) {
                        const box = document.getElementById({{ \Illuminate\Support\Js::from($boxId) }});
                        document.getElementById(`{{ $boxId }}-${id}`)?.remove();
                        if (! box.querySelector('.project-note-row')) {
                            box.insertAdjacentHTML('beforeend', {{ \Illuminate\Support\Js::from('<div class="project-notes-empty py-0.5 text-xs text-gray-400">'.e(__('– noch keine Einträge –')).'</div>') }});
                        }
                    }
                });
            },
        }"
    @endif
>
    <label class="block text-xs text-gray-500">{{ $label }}</label>

    <div id="{{ $boxId }}" class="mt-0.5 max-h-32 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 px-2 py-1">
        @forelse ($notes as $note)
            @include('projekte.partials.project-note-row', ['note' => $note, 'editable' => $editable, 'idPrefix' => $boxId])
        @empty
            <div class="project-notes-empty py-0.5 text-xs text-gray-400">{{ __('– noch keine Einträge –') }}</div>
        @endforelse
    </div>

    @if ($editable)
        <div class="mt-1" x-show="! adding">
            <button type="button" @click="adding = true" class="{{ $secondaryBtn }}">{{ __('Neu') }}</button>
        </div>
        <div class="mt-1 space-y-1" x-show="adding" x-cloak>
            <textarea x-model="text" rows="2" :disabled="saving" placeholder="{{ __('Text eingeben…') }}" class="w-full rounded-md border-gray-300 text-xs disabled:bg-gray-50"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" @click="adding = false; text = ''" :disabled="saving" class="{{ $secondaryBtn }}">{{ __('Abbrechen') }}</button>
                <button type="button" @click="addNote()" :disabled="saving" class="inline-flex items-center rounded-md bg-btn-primary px-2.5 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Speichern') }}</button>
            </div>
        </div>
    @endif
</div>
