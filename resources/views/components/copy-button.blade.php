{{--
    Wiederverwendbarer Kopieren-Button (Ralf, 2026-09-12: "das brauchen wir
    noch an einigen Stellen") - Icon wechselt kurz zu einem Haken als
    stille Erfolgsrückmeldung, kein Toast/Dialog nötig für so eine kleine
    Aktion. Nutzt window.copyToClipboard() (siehe layouts/app.blade.php).
--}}
@props(['text', 'label' => null])

<button
    type="button"
    x-data="{ copied: false }"
    @click="window.copyToClipboard({{ \Illuminate\Support\Js::from($text) }}).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
    :title="copied ? {{ \Illuminate\Support\Js::from(__('Kopiert!')) }} : {{ \Illuminate\Support\Js::from($label ?? __('In die Zwischenablage kopieren')) }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700']) }}
>
    <svg x-show="!copied" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
    </svg>
    <svg x-show="copied" x-cloak class="h-4 w-4 shrink-0 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
    </svg>
</button>
