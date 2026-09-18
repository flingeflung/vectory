@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'dirtyCheck' => null,
    'draggable' => false,
    'height' => null,
    'resizable' => false,
    'fullscreen' => false,
    // Ralf, 2026-09-15: "bei komplizierten Eingaben ist ein Klick daneben
    // sehr ärgerlich" - für Dialoge mit aufwändig auszufüllenden Formularen
    // (aktuell Multichange, Anzeigefilter) lässt sich das versehentliche
    // Schließen per Klick auf den abgedunkelten Hintergrund abschalten.
    // Escape und die eigenen Abbrechen/"X"-Buttons funktionieren immer
    // unverändert weiter (setzen intentional, nicht aus Versehen daneben).
    'closeOnBackdrop' => true,
    // Ralf-Bug-Report, 2026-09-13: Projektgruppen-Panel - Projekte per
    // Häkchen in der Tabelle markieren, WÄHREND das Panel offen ist, war
    // unmöglich, weil der übliche Backdrop den Klick abfängt. Für diesen
    // einen Anwendungsfall (Panel + Hintergrund gleichzeitig bedienbar)
    // "blocking" abschaltbar: kein abgedunkelter Hintergrund, keine
    // Klick-außerhalb-schließt-Geste, Seite bleibt scrollbar - alles
    // andere (Escape schließt, Ziehen usw.) bleibt unverändert.
    'blocking' => true,
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '5xl' => 'sm:max-w-5xl',
    '6xl' => 'sm:max-w-6xl',
    '7xl' => 'sm:max-w-7xl',
][$maxWidth];

// Statisch (nicht Alpine-reaktiv), da $height sich pro Aufruf nicht ändert:
// feste Höhe verhindert das "Springen" der Boxgröße beim Nachladen von Inhalt.
// Bei fester Höhe scrollt NICHT die Box selbst (das würde Header/Footer, die
// nur normale Flex-Kinder des Inhalts sind, mit hoch-/runterscrollen lassen)
// - stattdessen liefert der Inhalt selbst einen Flex-Layout mit einem intern
// scrollenden Mittelteil (siehe projekte/partials/detail.blade.php).
$boxOverflowClass = 'overflow-hidden';
$heightStyle = $height ? "height: {$height};" : '';
$storageKey = "vectory-modal-size-{$name}";
@endphp

<div
    x-data="{
        show: @js($show),
        dirtyCheckFn: {{ $dirtyCheck ? \Illuminate\Support\Js::from($dirtyCheck) : 'null' }},
        draggable: @js($draggable),
        resizable: @js($resizable),
        fullscreen: @js($fullscreen),
        maximize: false,
        savedNormalBox: null,
        savedNormalPos: null,
        dragPos: null,
        dragBox: null,
        dragging: false,
        resizing: false,
        closing: false,
        rememberNormalState() {
            const box = this.$refs.box;
            if (! box || this.maximize) {
                return;
            }

            const rect = box.getBoundingClientRect();
            this.savedNormalBox = {
                width: Math.max(0, Math.round(rect.width)),
                height: Math.max(0, Math.round(rect.height)),
            };
            this.savedNormalPos = {
                x: Math.max(0, Math.round(rect.left)),
                y: Math.max(0, Math.round(rect.top)),
            };
        },
        startDrag(e) {
            if (! this.draggable || e.target.closest('button, a, input, select, textarea')) {
                return;
            }

            e.preventDefault();
            window.getSelection()?.removeAllRanges();
            this.dragging = true;

            if (this.fullscreen && this.maximize) {
                this.maximize = false;
            }

            const box = e.currentTarget.closest('[data-modal-box]');
            const rect = box.getBoundingClientRect();

            if (! this.dragPos) {
                this.dragBox = { width: rect.width, height: rect.height };
            }

            const offsetX = e.clientX - rect.left;
            const offsetY = e.clientY - rect.top;

            const onMove = (moveEvent) => {
                window.getSelection()?.removeAllRanges();
                this.dragPos = {
                    x: Math.min(Math.max(0, moveEvent.clientX - offsetX), window.innerWidth - rect.width),
                    y: Math.min(Math.max(0, moveEvent.clientY - offsetY), window.innerHeight - rect.height),
                };
                this.rememberNormalState();
            };
            const onUp = () => {
                this.dragging = false;
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        },
        openPositioned() {
            this.maximize = false;
            this.savedNormalBox = null;
            this.savedNormalPos = null;
            this.applyBoxLayout();
        },
        applyBoxLayout() {
            if (this.fullscreen) {
                if (this.maximize) {
                    this.dragBox = { width: window.innerWidth, height: window.innerHeight };
                    this.dragPos = { x: 0, y: 0 };
                    return;
                }

                const margin = 10;
                const width = this.savedNormalBox?.width ?? Math.max(0, window.innerWidth - (margin * 2));
                const height = this.savedNormalBox?.height ?? Math.max(0, window.innerHeight - (margin * 2));
                const x = this.savedNormalPos?.x ?? margin;
                const y = this.savedNormalPos?.y ?? margin;

                this.dragBox = { width, height };
                this.dragPos = { x, y };
                return;
            }

            // Größe: gemerkte (localStorage) oder 3/4 der Bildschirmfläche als
            // Startgröße - der Nutzer kann per Resize-Griff (unten rechts)
            // selbst nachjustieren, das wird dann für künftige Aufrufe gemerkt.
            let stored = null;
            try {
                stored = JSON.parse(localStorage.getItem({{ \Illuminate\Support\Js::from($storageKey) }}) || 'null');
            } catch (e) {}

            const width = Math.min(stored?.width ?? Math.round(window.innerWidth * 0.75), window.innerWidth - 32);
            const height = Math.min(stored?.height ?? Math.round(window.innerHeight * 0.75), window.innerHeight - 32);

            this.dragBox = { width, height };
            this.dragPos = {
                x: Math.round((window.innerWidth - width) / 2),
                y: Math.round((window.innerHeight - height) / 2),
            };
        },
        toggleMaximize() {
            if (! this.fullscreen) {
                return;
            }

            this.dragging = false;
            this.resizing = false;

            const box = this.$refs.box;
            const rect = box ? box.getBoundingClientRect() : null;

            if (this.maximize) {
                this.maximize = false;
                const restoreBox = this.savedNormalBox ?? {
                    width: Math.max(480, Math.min(window.innerWidth - 20, rect ? rect.width : window.innerWidth * 0.75)),
                    height: Math.max(320, Math.min(window.innerHeight - 20, rect ? rect.height : window.innerHeight * 0.75)),
                };
                const restorePos = this.savedNormalPos ?? {
                    x: Math.max(10, Math.min(window.innerWidth - restoreBox.width - 10, Math.round((window.innerWidth - restoreBox.width) / 2))),
                    y: Math.max(10, Math.min(window.innerHeight - restoreBox.height - 10, Math.round((window.innerHeight - restoreBox.height) / 2))),
                };
                this.dragBox = { ...restoreBox };
                this.dragPos = { ...restorePos };
                return;
            }

            this.savedNormalBox = this.dragBox ? { ...this.dragBox } : (rect ? {
                width: Math.max(0, Math.round(rect.width)),
                height: Math.max(0, Math.round(rect.height)),
            } : null);
            this.savedNormalPos = this.dragPos ? { ...this.dragPos } : (rect ? {
                x: Math.max(0, Math.round(rect.left)),
                y: Math.max(0, Math.round(rect.top)),
            } : null);

            this.maximize = true;
            this.dragBox = { width: window.innerWidth, height: window.innerHeight };
            this.dragPos = { x: 0, y: 0 };
        },
        watchResize() {
            this.$nextTick(() => {
                const box = this.$refs.box;
                if (! box || box.dataset.resizeWatched) {
                    return;
                }
                box.dataset.resizeWatched = '1';

                let saveTimeout = null;
                let resizeEndTimeout = null;
                new ResizeObserver(() => {
                    if (! this.show) {
                        return;
                    }

                    if (this.fullscreen && this.maximize) {
                        this.dragBox = { width: window.innerWidth, height: window.innerHeight };
                        this.dragPos = { x: 0, y: 0 };
                        this.resizing = false;
                        return;
                    }

                    // Wie beim Verschieben: transition-all (fürs Öffnen/
                    // Schließen gedacht) sonst auch während des Resizens
                    // aktiv -> die Box eiert hinterher statt dem Mauszeiger
                    // direkt zu folgen.
                    this.resizing = true;
                    clearTimeout(resizeEndTimeout);
                    resizeEndTimeout = setTimeout(() => { this.resizing = false; }, 200);

                    // Alpines :style-Binding neu mit der tatsächlichen (per
                    // nativem Resize-Griff gezogenen) Größe synchron halten -
                    // sonst würde ein späteres Alpine-Rerender die Größe auf
                    // den alten Stand zurückspringen lassen.
                    this.dragBox = { width: box.offsetWidth, height: box.offsetHeight };

                    if (! this.maximize) {
                        this.rememberNormalState();
                    }

                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        localStorage.setItem(
                            {{ \Illuminate\Support\Js::from($storageKey) }},
                            JSON.stringify(this.dragBox)
                        );
                    }, 300);
                }).observe(box);
            });
        },
        async requestClose() {
            if (this.closing || !this.show) {
                return;
            }

            if (this.dirtyCheckFn && typeof window[this.dirtyCheckFn] === 'function' && window[this.dirtyCheckFn]()) {
                this.closing = true;
                const discard = await window.confirmDialog(
                    {{ \Illuminate\Support\Js::from(__('Es gibt ungespeicherte Änderungen. Trotzdem verwerfen?')) }}
                );
                this.closing = false;

                if (! discard) {
                    return;
                }
            }

            this.show = false;
        },
        focusables() {
            // All focusable element types...
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            {{ $blocking ? "document.body.classList.add('overflow-y-hidden');" : '' }}
            window.__modalStack = (window.__modalStack || []).filter(n => n !== '{{ $name }}');
            window.__modalStack.push('{{ $name }}');
            const stackIndex = (window.__modalStack || []).length;
            $el.style.zIndex = String(50 + stackIndex * 10);
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            {{ $blocking ? "document.body.classList.remove('overflow-y-hidden');" : '' }}
            window.__modalStack = (window.__modalStack || []).filter(n => n !== '{{ $name }}');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? (show ? null : (resizable ? openPositioned() : (dragPos = null)), show = true, resizable && watchResize()) : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? requestClose() : null"
    x-on:close.stop="requestClose()"
    x-on:keydown.escape.window="(window.__modalStack || [])[(window.__modalStack || []).length - 1] === '{{ $name }}' && requestClose()"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    data-modal-name="{{ $name }}"
    class="fixed inset-0 z-50 {{ $fullscreen ? 'overflow-hidden p-0' : 'overflow-y-auto px-4 py-6 sm:px-0' }} {{ $blocking ? '' : 'pointer-events-none' }}"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    @if ($blocking)
        <div
            x-show="show"
            class="fixed inset-0 transform transition-all"
            @if ($closeOnBackdrop)
                x-on:click="requestClose()"
            @endif
            @if (! $show)
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
            @endif
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
    @endif

    <div
        x-show="show"
        @unless ($show)
            x-cloak
        @endunless
        x-ref="box"
        data-modal-box
        x-on:mousedown="$event.target.closest('[data-drag-handle]') && startDrag($event)"
        :class="(dragPos ? '' : 'sm:mx-auto') + (dragging ? ' select-none' : '') + ((fullscreen && maximize) ? ' resize-none' : (resizable ? ' resize' : '')) + (fullscreen ? ' mx-0 my-0' : ' mb-6')"
        :style="`${(dragging || resizing) ? 'transition: none;' : ''}${dragPos ? `position: fixed; left: ${dragPos.x}px; top: ${dragPos.y}px; width: ${dragBox.width}px; height: ${dragBox.height}px; margin: 0;` : ''}${fullscreen ? ' min-width: 0; min-height: 0; max-width: none; max-height: none;' : ''}{{ $heightStyle }}${!fullscreen && {{ json_encode((bool) $resizable) }} ? 'min-width: 480px; min-height: 320px; max-width: 95vw; max-height: 92vh;' : ''}`"
        {{--
            Ralf-Bug-Report: "Overlay springt ganz nach links an den
            Bildrand" - "sm:mx-auto" (Zentrierung) kam bisher NUR über
            die :class-Alpine-Bindung oben, stand also NICHT im rohen
            Server-HTML. Solange x-cloak die Box versteckte, fiel das nie
            auf (Alpine hatte :class längst ausgewertet, bevor überhaupt
            etwas sichtbar wurde). Ohne x-cloak (siehe :show-Fälle wie
            reopen_group) ist der allererste Browser-Render aber genau
            dieser unzentrierte Rohzustand - deshalb "sm:mx-auto" jetzt
            zusätzlich statisch mitgeben. Harmlos bei echtem Ziehen
            (dragPos gesetzt): position:fixed mit explizitem left/top
            überschreibt einen simplen Auto-Margin ohnehin.
        --}}
        class="pointer-events-auto sm:mx-auto bg-white rounded-lg {{ $boxOverflowClass }} shadow-xl transform transition-all {{ $resizable ? '' : 'sm:w-full '.$maxWidth }}"
        @if (! $show)
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        @endif
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
