{{--
    Kurzer "Gespeichert."-Hinweis (Ralf, 2026-10-03): liegt als Overlay oben über allem, schiebt
    den Inhalt also weder nach unten noch zurück. Blendet nach $seconds Sekunden aus, mit
    Schließen-X für Ungeduldige. Per x-teleport an <body> gehängt, damit er auch aus einem
    Overlay/Modal heraus oben am Fenster erscheint. Die Klassen des Aufrufers (Schriftgröße,
    Innenabstand) gelten für die Meldung selbst. Gegenstück für JavaScript: window.showToast()
    in layouts/app.blade.php.
--}}
@props(['seconds' => 2])

<div
    x-data="{ show: true }"
    x-init="setTimeout(() => show = false, {{ (int) ($seconds * 1000) }})"
>
    <template x-teleport="body">
        <div
            x-show="show"
            x-transition.opacity
            role="status"
            class="pointer-events-none fixed inset-x-0 top-3 z-[100] flex justify-center px-4"
        >
            <div {{ $attributes->merge(['class' => 'rounded-md text-green-800'])->class(['pointer-events-auto flex items-start gap-3 border border-green-200 bg-green-50 shadow-lg']) }}>
                <div class="min-w-0">{{ $slot }}</div>
                <button type="button" @click="show = false" class="shrink-0 text-green-700/70 hover:text-green-900" aria-label="{{ __('Schließen') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    </template>
</div>
