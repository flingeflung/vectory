{{-- Reine Zeilen-Liste (ohne Neu/Abbrechen/Speichern) - wird sowohl beim
     ersten Rendern der Box (project-notes.blade.php) als auch beim
     Live-Abgleich zwischen mehreren Boxen desselben Typs verwendet
     (ProjectNoteController::index(), siehe project-notes.blade.php:
     refresh()). Braucht $notes, $editable, $boxId. --}}
@forelse ($notes as $note)
    @include('projekte.partials.project-note-row', ['note' => $note, 'editable' => $editable, 'idPrefix' => $boxId])
@empty
    <div class="py-0.5 text-xs text-gray-400">{{ __('– noch keine Einträge –') }}</div>
@endforelse
