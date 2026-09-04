@props(['status'])

@php
    // Bewusst Viettos eigene Icons (blnFin0-3.gif), nicht neu nachgebaut -
    // Ralf wollte explizit "die schicken Symbole aus Vietto".
    $files = [0 => 'blnFin0.gif', 1 => 'blnFin1.gif', 2 => 'blnFin2.gif', 3 => 'blnFin3.gif'];
    $labels = [0 => __('geplant'), 1 => __('in Bearbeitung'), 2 => __('beendet'), 3 => __('verworfen')];
@endphp

@if (isset($files[$status]))
    <img
        src="{{ asset('images/status-icons/'.$files[$status]) }}"
        alt="{{ $labels[$status] }}"
        title="{{ $labels[$status] }}"
        {{ $attributes->merge(['class' => 'inline-block h-4 w-auto align-middle']) }}
    >
@endif
