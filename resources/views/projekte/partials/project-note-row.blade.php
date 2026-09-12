{{-- Eine Zeile Bemerkung/Änderung zur Vorversion - eigene Partial, damit
     ProjectNoteController::store() nach dem Anlegen NUR diese eine Zeile
     zurückgeben kann (gleiches Prinzip wie bei den Projektverknüpfungen).
     Braucht $note, $editable (ob überhaupt ein Löschen-Icon gezeigt werden
     kann - der ×-Klick selbst ist zusätzlich per @click in
     project-notes.blade.php auf Autor/Super-Admin beschränkt) und
     $idPrefix (eindeutige Element-ID je Box - dieselbe Bemerkung erscheint
     ggf. zusätzlich schreibgeschützt in der Ablaufdaten-Box, IDs dürfen
     im HTML nicht doppelt vorkommen). --}}
<div class="project-note-row border-b border-gray-100 py-1 last:border-0" id="{{ $idPrefix }}-{{ $note->id }}">
    <div class="flex items-start justify-between gap-2">
        <div class="text-[11px] text-gray-500">
            <span class="font-medium text-gray-700">{{ $note->createdByUser->name }}</span>,
            {{ $note->created_at->format('d.m.Y, H:i:s') }} {{ __('Uhr') }}
        </div>
        @if ($editable && (auth()->id() === $note->created_by_user_id || auth()->user()->role === 'super_admin'))
            <button
                type="button"
                @click="deleteNote({{ $note->id }})"
                class="shrink-0 text-gray-300 hover:text-red-600"
                title="{{ __('Löschen') }}"
            >&times;</button>
        @endif
    </div>
    <div class="whitespace-pre-wrap text-xs text-gray-700">{{ $note->text }}</div>
</div>
