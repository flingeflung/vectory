{{-- Abwesenheits-Markierung (Ralf, 2026-09-12, Backlog-Punkt "Abwesenheits-
     Markierung") - neutrales Icon statt urlaubsspezifischer Bildsprache
     (z.B. Palme), da Abwesenheit auch Krankheit o.ä. bedeuten kann und das
     Tool international eingesetzt wird. Tooltip nennt zusätzlich das
     "bis wann"-Datum, falls gesetzt (siehe Person::isCurrentlyAbsent()).
     Rendert nichts, wenn die Person aktuell nicht abwesend ist. --}}
@props(['person'])

@if ($person->isCurrentlyAbsent())
    <span
        class="inline-block align-middle text-amber-500"
        title="{{ $person->absent_until ? __('Als abwesend markiert bis :date', ['date' => $person->absent_until->format('d.m.Y')]) : __('Als abwesend markiert') }}"
    >
        <svg class="inline h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
        </svg>
    </span>
@endif
