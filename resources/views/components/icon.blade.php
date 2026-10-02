{{--
    Lädt ein Icon aus resources/icons/<name>.svg und gibt es inline aus, damit
    `currentColor` (Tailwind text-*) greift. Ralf, 2026-10-02: Icons sollen als
    eigene Dateien vorliegen, damit er sie nachträglich austauschen kann.
    Größe per class (Default h-4 w-4).
--}}
@props(['name'])
@php
    $file = resource_path('icons/' . basename($name) . '.svg');
    $svg = is_file($file) ? file_get_contents($file) : '';
    $svg = preg_replace('/<svg\b/', '<svg ' . $attributes->merge(['class' => 'h-4 w-4', 'aria-hidden' => 'true']), $svg, 1);
@endphp
{!! $svg !!}
