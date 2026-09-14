@props(['field', 'sort' => null, 'direction' => 'asc'])

@php
    $isActive = $sort === $field;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    // Ralf-Bug-Report, 2026-09-14: fullUrlWithQuery() übernimmt ALLE
    // aktuellen Query-Parameter - auch das einmalige reopen_group-Signal
    // (siehe projekte/index.blade.php), das eigentlich nur den einen
    // Redirect nach "Projekte dieser Gruppe anzeigen" überlebt und dann per
    // history.replaceState() aus der Adressleiste entfernt wird. Der schon
    // server-seitig gerenderte Sortier-Link kannte diese Bereinigung aber
    // nicht und trug reopen_group bei jedem Sortieren weiter - das
    // Gruppieren-Overlay ging dadurch immer wieder unerwünscht auf.
    $sortQuery = array_merge(request()->except('reopen_group'), ['sort' => $field, 'direction' => $nextDirection, 'page' => 1]);
@endphp

<th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">
    <a
        href="{{ request()->url().'?'.http_build_query($sortQuery) }}"
        class="inline-flex items-center gap-1 hover:text-gray-700 {{ $isActive ? 'text-gray-900 font-semibold' : '' }}"
    >
        {{ $slot }}
        <span class="w-3 {{ $isActive ? 'text-indigo-600' : 'text-gray-300' }}">
            {{ $isActive ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}
        </span>
    </a>
</th>
