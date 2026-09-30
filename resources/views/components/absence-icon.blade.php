@props(['person'])

@php
    $absenceEntries = $person->currentAbsenceEntries();
    $firstDate = $absenceEntries->min(fn ($entry) => $entry->starts_on->toDateString());
    $lastDate = $absenceEntries->max(fn ($entry) => $entry->ends_on->toDateString());
    $notes = $absenceEntries->pluck('note')->filter()->unique()->implode('; ');
    $title = null;
    if ($firstDate && $lastDate) {
        $title = $firstDate === $lastDate
            ? __('Abwesenheit am :date', ['date' => \Carbon\CarbonImmutable::parse($firstDate)->format('d.m.Y')])
            : __('Abwesenheit vom :from bis :to', [
                'from' => \Carbon\CarbonImmutable::parse($firstDate)->format('d.m.Y'),
                'to' => \Carbon\CarbonImmutable::parse($lastDate)->format('d.m.Y'),
            ]);
        $title .= $notes !== '' ? ' ('.$notes.')' : '';
    }
@endphp

@if ($absenceEntries->isNotEmpty())
    <span
        class="inline-block align-middle text-amber-500"
        title="{{ $title }}"
    >
        <svg class="inline h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
        </svg>
    </span>
@endif
