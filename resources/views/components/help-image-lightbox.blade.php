{{--
    Bild-Vergrößerung für Hilfeseiten-Bilder (Ralf, 2026-09-12: "bitte als
    Overlay über das Overlay, nicht in einem extra Browser-Tab") - zweite
    <x-modal>-Instanz, die einfach über der schon offenen help-panel
    aufklappt (Modal-Stack unterstützt das bereits, siehe company-manager
    über person-overlay). Bildquelle kommt nicht als Event-Detail (das
    prüft x-modal nur auf Gleichheit mit dem Namen), sondern über die
    globale window.__helpLightboxSrc, gesetzt vom Klick-Handler in
    help-panel.blade.php.
--}}
<x-modal name="help-image-lightbox" max-width="5xl">
    <div
        class="relative"
        x-data
        x-on:open-modal.window="$event.detail === 'help-image-lightbox' && ($refs.img.src = window.__helpLightboxSrc || '')"
    >
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'help-image-lightbox' }))"
            class="absolute right-2 top-2 rounded-full bg-white/90 p-1.5 text-gray-600 shadow hover:text-gray-900"
            aria-label="{{ __('Schließen') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        <img x-ref="img" alt="" class="max-h-[85vh] w-full rounded-lg object-contain">
    </div>
</x-modal>
