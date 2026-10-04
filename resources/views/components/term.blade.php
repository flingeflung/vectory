@props(['name' => null])
{{-- Begriff aus dem Begriffsverzeichnis (siehe App\Models\GlossaryTerm): <x-term>Grundlast</x-term> oder <x-term name="Grundlast">Grundlast-Eintrag</x-term> --}}
{!! \App\Models\GlossaryTerm::link($name ?? trim((string) $slot), trim((string) $slot)) !!}
