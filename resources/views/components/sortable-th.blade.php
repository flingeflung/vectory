@props(['field', 'sort' => null, 'direction' => 'asc'])

@php
    $isActive = $sort === $field;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
@endphp

<th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">
    <a
        href="{{ request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDirection, 'page' => 1]) }}"
        class="inline-flex items-center gap-1 hover:text-gray-700 {{ $isActive ? 'text-gray-900 font-semibold' : '' }}"
    >
        {{ $slot }}
        <span class="w-3 {{ $isActive ? 'text-indigo-600' : 'text-gray-300' }}">
            {{ $isActive ? ($direction === 'asc' ? '▲' : '▼') : '↕' }}
        </span>
    </a>
</th>
