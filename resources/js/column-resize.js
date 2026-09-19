/**
 * Persönlich einstellbare Spaltenbreiten (Ralf, 2026-09-19) - wiederverwendbar für
 * jede große Tabellenansicht:
 *
 *   <div x-data="columnResize('tabellen-schluessel', @js($gespeicherteBreiten), '/tabellenbreiten/…')">
 *       <table> <thead><tr> <th data-col="spaltenschluessel"> …
 *
 * Nur <th> mit data-col bekommen einen Ziehgriff. Ohne gespeicherte Breiten bleibt
 * das automatische Tabellenlayout unverändert. Beim ersten Ziehen (oder beim Laden
 * mit gespeicherten Breiten) wechselt die Tabelle auf festes Layout mit exakten
 * Pixelbreiten - die Breiten hängen an den Spaltenschlüsseln, nicht an der Position,
 * damit ein Wechsel der sichtbaren Spalten nichts verschiebt.
 */
const MIN_WIDTH = 40;
const MAX_WIDTH = 1200;
const DEFAULT_WIDTH = 160;
const UNKEYED_WIDTH = 40;

const clamp = (value) => Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, Math.round(value)));

export default function columnResize(tableKey, savedWidths, url) {
    return {
        table: null,
        frozen: false,

        init() {
            this.table = this.$el.querySelector('table');
            if (!this.table || !this.table.tHead) return;

            this.headers().forEach((th) => this.addHandle(th));

            if (savedWidths && Object.keys(savedWidths).length > 0) {
                this.freeze((th) => (th.dataset.col ? savedWidths[th.dataset.col] ?? DEFAULT_WIDTH : UNKEYED_WIDTH));
            }

            // Spalten können per x-show ein-/ausgeblendet werden (z.B. Häkchen-Spalte) -
            // die Gesamtbreite muss dann neu berechnet werden.
            new MutationObserver(() => this.syncTableWidth()).observe(this.table.tHead, {
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class'],
            });
        },

        headers() {
            return Array.from(this.table.tHead.rows[0].cells);
        },

        isVisible(th) {
            return th.getClientRects().length > 0;
        },

        addHandle(th) {
            if (!th.dataset.col) return;

            if (getComputedStyle(th).position === 'static') th.style.position = 'relative';

            const handle = document.createElement('span');
            handle.className = 'col-resize-handle';
            handle.title = 'Ziehen: Breite ändern · Doppelklick: alle Spaltenbreiten zurücksetzen';
            handle.addEventListener('pointerdown', (event) => this.startDrag(event, th, handle));
            handle.addEventListener('dblclick', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.reset();
            });
            // Kein Klick durchreichen (Sortier-Link in der Überschrift).
            handle.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
            });
            th.appendChild(handle);
        },

        /** Feste Pixelbreiten für ALLE Spalten (auch ausgeblendete) setzen. */
        freeze(widthFor) {
            this.headers().forEach((th) => {
                th.style.width = `${clamp(widthFor(th))}px`;
            });
            this.table.classList.add('col-resize-fixed');
            this.frozen = true;
            this.syncTableWidth();
        },

        syncTableWidth() {
            if (!this.frozen) return;
            const total = this.headers()
                .filter((th) => this.isVisible(th))
                .reduce((sum, th) => sum + (parseFloat(th.style.width) || 0), 0);
            // Exakte Summe statt 100 %: sonst verteilt das feste Layout überschüssige
            // Breite anteilig auf alle Spalten und die eingestellten Breiten stimmen nicht mehr.
            this.table.style.width = `${total}px`;
            this.table.style.minWidth = '0';
        },

        startDrag(event, th, handle) {
            event.preventDefault();
            event.stopPropagation();

            if (!this.frozen) {
                // Erstes Ziehen: aktuelle, automatisch berechnete Breiten einfrieren.
                const measured = new Map(this.headers().filter((h) => this.isVisible(h)).map((h) => [h, h.getBoundingClientRect().width]));
                this.freeze((h) => measured.get(h) ?? (h.dataset.col ? DEFAULT_WIDTH : UNKEYED_WIDTH));
            }

            const startX = event.clientX;
            const startWidth = parseFloat(th.style.width) || th.getBoundingClientRect().width;
            handle.setPointerCapture(event.pointerId);
            document.body.classList.add('col-resizing');

            const move = (moveEvent) => {
                th.style.width = `${clamp(startWidth + moveEvent.clientX - startX)}px`;
                this.syncTableWidth();
            };
            const stop = () => {
                handle.removeEventListener('pointermove', move);
                handle.removeEventListener('pointerup', stop);
                handle.removeEventListener('pointercancel', stop);
                document.body.classList.remove('col-resizing');
                this.save();
            };
            handle.addEventListener('pointermove', move);
            handle.addEventListener('pointerup', stop);
            handle.addEventListener('pointercancel', stop);
        },

        async save() {
            const widths = {};
            this.headers().forEach((th) => {
                if (th.dataset.col) widths[th.dataset.col] = clamp(parseFloat(th.style.width) || DEFAULT_WIDTH);
            });
            try {
                await fetch(url, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ widths }),
                });
            } catch (error) {
                // Speichern ist Komfort - die Breiten gelten in dieser Ansicht trotzdem weiter.
            }
        },

        async reset() {
            this.headers().forEach((th) => { th.style.width = ''; });
            this.table.classList.remove('col-resize-fixed');
            this.table.style.width = '';
            this.table.style.minWidth = '';
            this.frozen = false;
            try {
                await fetch(url, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
            } catch (error) {
                // siehe save()
            }
        },
    };
}
