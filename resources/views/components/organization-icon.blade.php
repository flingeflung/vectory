@props(['organization'])

@if ($organization?->iconUrl())
    <img
        src="{{ $organization->iconUrl() }}"
        alt=""
        title="{{ $organization->name }}"
        {{ $attributes->class(['h-4 w-4 shrink-0 object-contain']) }}
    >
@else
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        aria-hidden="true"
        title="{{ $organization?->name ?? __('Organisation') }}"
        {{ $attributes->class(['h-4 w-4 shrink-0 text-gray-400']) }}
    >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 21h16M6 21V5.5L12 3v18m6 0V9l-6-2.5M8.5 8h1m-1 3h1m-1 3h1m5-3h1m-1 3h1m-7 3h1m5 0h1" />
    </svg>
@endif
