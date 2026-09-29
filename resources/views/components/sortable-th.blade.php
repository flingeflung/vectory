@props(['field', 'sort' => null, 'direction' => 'asc', 'align' => 'left', 'compact' => false])

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
    $spacing = $compact ? 'px-3 py-2' : 'px-4 py-3';
    $alignment = $align === 'right' ? 'text-right' : 'text-left';
    $linkAlignment = $align === 'right' ? 'w-full justify-end' : '';
@endphp

<th data-col="{{ $field }}" class="sticky top-0 z-10 bg-gray-50 {{ $spacing }} {{ $alignment }} font-medium text-gray-500 whitespace-nowrap">
    <a
        href="{{ request()->url().'?'.http_build_query($sortQuery) }}"
        class="inline-flex items-center gap-1 hover:text-gray-700 {{ $linkAlignment }} {{ $isActive ? 'text-gray-900 font-semibold' : '' }}"
    >
        {{ $slot }}
        <span class="w-3 {{ $isActive ? 'text-indigo-600' : 'text-gray-300' }}">
            {{ $isActive ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}
        </span>
    </a>
</th>
