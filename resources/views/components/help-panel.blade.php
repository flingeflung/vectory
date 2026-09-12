{{--
    Hilfe-Panel (Ralf, 2026-09-12) - "?"-Button in der Topbar öffnet dieses
    Overlay (gleiches <x-modal>-Muster wie company-manager & Co., bewusst
    KEIN eigenes Slide-over-Layout - Wiederverwendung der vorhandenen
    Overlay-Mechanik: Escape/Backdrop/Modal-Stack funktionieren dadurch
    automatisch mit, auch übereinander mit einem bereits offenen Projekt-/
    Personen-Overlay). Zeigt beim Öffnen ohne Sucheingabe den Artikel zur
    aktuellen Seite (window.currentHelpKey, siehe layouts/app.blade.php),
    Tippen im Suchfeld schaltet auf Stichwort-Treffer um.

    Bewusst KEIN window.liveFilterSearch() (siehe layouts/app.blade.php) -
    das schreibt die Such-URL per history.replaceState() in die Adresszeile,
    was hier die eigentliche Seiten-URL überschreiben würde. Eigene,
    schlanke Debounce-Funktion ohne History-Nebenwirkung stattdessen.
--}}
<x-modal name="help-panel" max-width="lg" :draggable="true">
    <div
        class="flex max-h-[80vh] flex-col"
        x-data="{
            refresh(delay = 0) {
                clearTimeout(this.timer);
                this.timer = setTimeout(async () => {
                    const params = new URLSearchParams({
                        route: window.currentHelpKey || '',
                        q: this.$refs.searchInput.value,
                    });
                    const html = await fetch({{ \Illuminate\Support\Js::from(route('hilfe')) }} + '?' + params.toString()).then((r) => r.text());
                    const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('help-results');
                    const current = document.getElementById('help-results');
                    if (fresh && current) {
                        current.innerHTML = fresh.innerHTML;
                    }
                }, delay);
            },
        }"
        x-on:open-modal.window="if ($event.detail === 'help-panel') {
            $refs.searchInput.value = '';
            refresh();
            $nextTick(() => $refs.searchInput.focus());
        }"
    >
        <div
            class="flex shrink-0 cursor-move items-center justify-between border-b border-gray-200 px-4 py-3"
            data-drag-handle
            title="{{ __('Ziehen zum Verschieben') }}"
        >
            <h3 class="text-sm font-semibold text-gray-900">{{ __('Hilfe') }}</h3>
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'help-panel' }))"
                class="text-gray-400 hover:text-gray-600"
                aria-label="{{ __('Schließen') }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="shrink-0 border-b border-gray-200 p-3">
            <input
                type="text"
                x-ref="searchInput"
                placeholder="{{ __('Hilfe durchsuchen…') }}"
                autocomplete="off"
                @input="refresh(400)"
                class="w-full rounded-md border-gray-300 text-sm"
            >
        </div>
        {{-- Bilder in Hilfeartikeln (siehe HelpArticleTranslation::bodyHtml())
             sind per CSS klein gehalten - Klick öffnet die Originaldatei in
             Originalgröße als eigenes Overlay ÜBER diesem Panel (siehe
             components/help-image-lightbox.blade.php), bewusst kein neuer
             Browser-Tab (Ralf: "macht wieder zu viel Arbeit, es zu erkennen
             und wieder zu schließen"). "[[Artikel-Titel]]"-Verweise
             (data-help-key, siehe HelpArticleTranslation::bodyHtml())
             springen im selben Panel zum Zielartikel statt echt zu
             navigieren. Event-Delegation, weil #help-results bei jeder
             Suche/jedem Artikelwechsel per innerHTML ausgetauscht wird. --}}
        <div
            class="min-h-0 flex-1 overflow-y-auto px-4 py-3"
            x-on:click="if ($event.target.tagName === 'IMG') {
                window.__helpLightboxSrc = $event.target.src;
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'help-image-lightbox' }));
            } else if ($event.target.closest('[data-help-key]')) {
                $event.preventDefault();
                window.helpOpenArticle($event.target.closest('[data-help-key]').dataset.helpKey);
            }"
        >
            <div id="help-results">{{ __('Lädt…') }}</div>
        </div>
    </div>
</x-modal>

<script>
    window.helpOpenArticle = async function (key) {
        const html = await fetch({{ \Illuminate\Support\Js::from(url('/hilfe/artikel')) }} + '/' + encodeURIComponent(key)).then((r) => r.text());
        const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('help-results');
        const current = document.getElementById('help-results');
        if (fresh && current) {
            current.innerHTML = fresh.innerHTML;
        }
    };
</script>
