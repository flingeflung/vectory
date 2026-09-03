@props(['market'])

@if ($market->iconUrl())
    <img src="{{ $market->iconUrl() }}" alt="{{ $market->country_iso }}" title="{{ $market->country_name }}; {{ $market->language_name }}" class="mr-0.5 inline-block h-3 w-auto align-middle">
@else
    <span class="mr-0.5 inline-block rounded bg-gray-100 px-1 align-middle text-[10px] text-gray-500" title="{{ $market->country_name }}; {{ $market->language_name }}">{{ $market->country_iso }}</span>
@endif
