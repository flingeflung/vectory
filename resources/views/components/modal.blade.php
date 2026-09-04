@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl',
    'dirtyCheck' => null,
    'draggable' => false,
    'height' => null,
    'resizable' => false,
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
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
        dragPos: null,
        dragBox: null,
        dragging: false,
        resizing: false,
        closing: false,
        startDrag(e) {
            if (! this.draggable || e.target.closest('button, a, input, select, textarea')) {
                return;
            }

            e.preventDefault();
            window.getSelection()?.removeAllRanges();
            this.dragging = true;

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
            document.body.classList.add('overflow-y-hidden');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? (show ? null : (resizable ? openPositioned() : (dragPos = null)), show = true, resizable && watchResize()) : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? requestClose() : null"
    x-on:close.stop="requestClose()"
    x-on:keydown.escape.window="requestClose()"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-show="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="fixed inset-0 transform transition-all"
        x-on:click="requestClose()"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>

    <div
        x-show="show"
        x-ref="box"
        data-modal-box
        x-on:mousedown="$event.target.closest('[data-drag-handle]') && startDrag($event)"
        :class="(dragPos ? '' : 'sm:mx-auto') + (dragging ? ' select-none' : '') + (resizable ? ' resize' : '')"
        :style="`${(dragging || resizing) ? 'transition: none;' : ''}${dragPos ? `position: fixed; left: ${dragPos.x}px; top: ${dragPos.y}px; width: ${dragBox.width}px; margin: 0;` : ''}${resizable && dragBox ? `height: ${dragBox.height}px;` : ''}{{ $heightStyle }}{{ $resizable ? 'min-width: 480px; min-height: 320px; max-width: 95vw; max-height: 92vh;' : '' }}`"
        class="mb-6 bg-white rounded-lg {{ $boxOverflowClass }} shadow-xl transform transition-all {{ $resizable ? '' : 'sm:w-full '.$maxWidth }}"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        {{ $slot }}
    </div>
</div>
