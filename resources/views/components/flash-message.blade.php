@props(['seconds' => 2])

<div
    x-data="{ show: true }"
    x-show="show"
    x-transition
    x-init="setTimeout(() => show = false, {{ (int) ($seconds * 1000) }})"
    {{ $attributes->merge(['class' => 'rounded bg-green-50 text-green-700']) }}
>{{ $slot }}</div>
