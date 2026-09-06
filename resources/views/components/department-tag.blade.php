{{--
    Abteilungs-Kürzel neben einem Personennamen (grau, in Klammern, voller
    Abteilungsname als Tooltip) - Ralfs Anforderung für Rechte- und
    Funktionsgruppen-Verwaltung. Erscheint nur, wenn die Abteilung ein
    Kürzel hat (sonst nichts anzeigen, kein Fallback auf den vollen Namen).
--}}
@props(['person'])
@if ($person->department?->short_name)
    <span class="text-gray-400" title="{{ $person->department->name }}">({{ $person->department->short_name }})</span>
@endif
