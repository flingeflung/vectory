@php
    // boxKey unterscheidet die Box, wenn derselbe $type mehrfach im
    // Overlay erscheint (Ralf, 2026-09-12: "Bemerkungen" gibt es
    // gleichwertig editierbar in Stammdaten UND Ablaufdaten) - reicht
    // nicht, nur nach editable/echo zu unterscheiden, seit beide
    // editierbar sind.
    $boxId = 'project-notes-'.$boxKey.'-'.$project->id;
    $smallBtn = 'rounded border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-50';
@endphp

{{-- Ralf, 2026-09-12 (Vietto-Vorbild "bemerkungen", intTyp 1=Bemerkung/
     2=Änderungsprotokoll): gemeinsame Komponente für beide Felder, $type
     unterscheidet die Datenbank-Zeilen (ProjectNote::TYPE_REMARK/
     TYPE_CHANGE). Jeder Eintrag trägt Autor + Zeitpunkt und wird sofort
     gespeichert bzw. gelöscht (kein Speichern-Button, kein Freigabe-
     Workflow - Ralf: "wie eine E-Mail, die wird ja auch nicht
     freigegeben"), deshalb eigenständige Fetches statt Teil des großen
     Projekt-Formulars (gleicher Grund wie bei den Projektverknüpfungen:
     ein <form> im <form> reißt sonst Felder ins äußere Formular mit rein).

     "Bemerkungen" erscheint zweimal im selben Overlay (editierbar in
     Stammdaten, siehe system-fields/remarks.blade.php, UND editierbar in
     Ablaufdaten, siehe system-fields/remarks_echo.blade.php - Ralf,
     2026-09-12: beide sollen gleichwertig bedienbar sein, nicht nur eine
     read-only Kopie). Damit ein in der einen Box angelegter/gelöschter
     Eintrag auch in der anderen sofort auftaucht, feuert jede Box nach
     einer Änderung ein window-Event "project-notes-changed" mit
     {type, projectId}; jede Box lauscht selbst darauf (auch die, die die
     Änderung ausgelöst hat) und lädt ihren Inhalt dann per
     ProjectNoteController::index() neu - einfacher und robuster als
     Fragment-Einfügen mit Sonderfällen für "war das schon die letzte
     Zeile" o.ä., und bei der kleinen Anzahl Einträge performt das genauso
     unauffällig.

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
            init() {
                this.onChanged = (e) => {
                    if (e.detail.type === {{ \Illuminate\Support\Js::from($type) }} && e.detail.projectId === {{ $project->id }}) {
                        this.refresh();
                    }
                };
                window.addEventListener('project-notes-changed', this.onChanged);
            },
            destroy() {
                window.removeEventListener('project-notes-changed', this.onChanged);
            },
            async refresh() {
                const url = {{ \Illuminate\Support\Js::from(route('projekte.notizen.index', $project)) }} + `?type=` + encodeURIComponent({{ \Illuminate\Support\Js::from($type) }}) + `&box_id=` + encodeURIComponent({{ \Illuminate\Support\Js::from($boxId) }});
                const response = await fetch(url);
                if (! response.ok) return;
                const box = document.getElementById({{ \Illuminate\Support\Js::from($boxId) }});
                if (box) box.innerHTML = await response.text();
            },
            notifyChanged() {
                window.dispatchEvent(new CustomEvent('project-notes-changed', { detail: { type: {{ \Illuminate\Support\Js::from($type) }}, projectId: {{ $project->id }} } }));
            },
            async addNote() {
                if (! this.text.trim()) return;
                this.saving = true;
                try {
                    const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.notizen.store', $project)) }}, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ type: {{ \Illuminate\Support\Js::from($type) }}, text: this.text }),
                    });
                    if (! response.ok) {
                        window.notifyDialog({{ \Illuminate\Support\Js::from(__('Speichern fehlgeschlagen. Bitte erneut versuchen.')) }});
                        return;
                    }
                    this.text = '';
                    this.adding = false;
                    this.notifyChanged();
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
                    if (response.ok) this.notifyChanged();
                });
            },
        }"
    @endif
>
    <label class="block text-xs text-gray-500">{{ $label }}</label>

    <div id="{{ $boxId }}" class="mt-0.5 max-h-32 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 px-2 py-1">
        @include('projekte.partials.project-notes-rows', ['notes' => $notes, 'editable' => $editable, 'boxId' => $boxId])
    </div>

    @if ($editable)
        <div class="mt-1" x-show="! adding">
            <button type="button" @click="adding = true" class="{{ $smallBtn }}">{{ __('Neu') }}</button>
        </div>
        <div class="mt-1 space-y-1" x-show="adding" x-cloak>
            <textarea x-model="text" rows="2" :disabled="saving" placeholder="{{ __('Text eingeben…') }}" class="w-full rounded-md border-gray-300 text-xs disabled:bg-gray-50"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" @click="adding = false; text = ''" :disabled="saving" class="{{ $smallBtn }}">{{ __('Abbrechen') }}</button>
                <button type="button" @click="addNote()" :disabled="saving" class="rounded bg-btn-primary px-2 py-0.5 text-[11px] font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Speichern') }}</button>
            </div>
        </div>
    @endif
</div>
