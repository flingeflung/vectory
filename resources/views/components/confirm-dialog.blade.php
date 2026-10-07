{{--
    Globaler, gestylter Bestätigungs-/Hinweis-Dialog als Ersatz für die
    nativen confirm()/alert() Browser-Dialoge - die will Ralf in Vectory
    grundsätzlich nicht mehr sehen (siehe CLAUDE.md UI-Konventionen).
    Aufruf aus JS:
      const ok = await window.confirmDialog('Text …');
      const ok = await window.confirmDialog({ title, message, confirmLabel, cancelLabel });
      Warnhinweis nach DIN EN 82079-1 / ISO 3864 (Ralf, 2026-10-07): zusätzlich signal ('hinweis' | 'achtung' | 'vorsicht' | 'warnung' | 'gefahr')
      und consequence (Folge als schlichter Satz, ohne Beschriftung); message nennt Art und Quelle. Ohne signal bleibt der bisherige Dialog.
      await window.notifyDialog('Text …'); // reiner Hinweis, nur ein OK-Button
--}}
<div
    x-data="{
        show: false, title: '', message: '', confirmLabel: '', cancelLabel: '', alertOnly: false, resolve: null,
        signal: '', consequence: '',
        // Signalwort-Stufen: Farbe des Kopfstreifens und des Bestätigen-Knopfs
        levels: {
            hinweis: { word: @js(__('Hinweis')), band: 'bg-blue-600 text-white', button: 'bg-btn-primary hover:bg-btn-primary-hover' },
            // ohne Signalwort: Achtung ist in der Norm für Personenschäden gedacht, hier genügt das Warndreieck (Ralf, 2026-10-07)
            achtung: { word: '', band: 'bg-amber-400 text-gray-900', button: 'bg-amber-600 hover:bg-amber-700' },
            vorsicht: { word: @js(__('Vorsicht')), band: 'bg-yellow-300 text-gray-900', button: 'bg-yellow-600 hover:bg-yellow-700' },
            warnung: { word: @js(__('Warnung')), band: 'bg-orange-500 text-white', button: 'bg-orange-600 hover:bg-orange-700' },
            gefahr: { word: @js(__('Gefahr')), band: 'bg-red-600 text-white', button: 'bg-red-600 hover:bg-red-700' },
        },
        get level() { return this.levels[this.signal] || null; },
    }"
    x-on:open-confirm-dialog.window="
        title = $event.detail.title;
        message = $event.detail.message;
        signal = $event.detail.signal || '';
        consequence = $event.detail.consequence || '';
        confirmLabel = $event.detail.confirmLabel;
        cancelLabel = $event.detail.cancelLabel;
        alertOnly = $event.detail.alertOnly;
        resolve = $event.detail.resolve;
        // Erst im nächsten Tick öffnen: wird dieser Dialog per Escape ausgelöst
        // (z.B. aus dem Schließen-Handler eines anderen Modals), läuft das
        // ursprüngliche Escape-Keydown-Event noch - würde show hier sofort auf
        // true springen, sähe der eigene Escape-Listener unten (selbes Event,
        // gleiches Fenster) show=true und würde sich selbst sofort wieder
        // schließen, bevor der Nutzer je etwas sieht.
        $nextTick(() => show = true)
    "
    x-show="show"
    x-cloak
    x-on:keydown.escape.window="if (show) { show = false; resolve(alertOnly ? true : false); }"
    {{-- z-[1000] statt z-[60]: siehe delete-confirm-dialog.blade.php - bei
         verschachtelten <x-modal>-Fenstern (gestaffelte Rangfolge seit der
         Gantt-Vollbild-Funktion) landete dieser globale Dialog sonst
         unsichtbar dahinter. --}}
    class="fixed inset-0 z-[1000] overflow-y-auto"
    style="display: none"
>
    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="show"
            class="fixed inset-0 bg-gray-500/75 transition-opacity"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>

        <div
            x-show="show"
            class="relative w-full overflow-hidden rounded-lg bg-white p-5 shadow-xl"
            :class="signal ? 'max-w-md' : 'max-w-sm'"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        >
            {{-- Kopfstreifen mit Warndreieck und Signalwort (nur bei signal) --}}
            <div x-show="level" class="-mx-5 -mt-5 mb-4 flex items-center gap-2 px-5 py-2 text-sm font-bold uppercase tracking-wider" :class="level ? level.band : ''">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5l9.5 16.5h-19L12 3.5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v4.5M12 17.2v.05" />
                </svg>
                <span x-text="level ? level.word : ''"></span>
            </div>
            <div x-show="signal" class="space-y-2">
                <h3 class="text-sm font-semibold text-gray-900" x-text="title"></h3>
                <p class="text-sm text-gray-700" x-text="message"></p>
                <p x-show="consequence" class="text-sm text-gray-700" x-text="consequence"></p>
            </div>
            <div x-show="! signal" class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.007v.008H12v-.008ZM21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
                    </svg>
                </div>
                <div class="pt-1">
                    <h3 class="text-sm font-semibold text-gray-900" x-text="title"></h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="message"></p>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    x-show="!alertOnly"
                    @click="show = false; resolve(false)"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    x-text="cancelLabel"
                ></button>
                <button
                    type="button"
                    @click="show = false; resolve(true)"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-white"
                    :class="alertOnly ? 'bg-btn-primary hover:bg-btn-primary-hover' : (level ? level.button : 'bg-red-600 hover:bg-red-700')"
                    x-text="confirmLabel"
                ></button>
            </div>
        </div>
    </div>
</div>

<script>
    window.confirmDialog = (options) => new Promise((resolve) => {
        const opts = typeof options === 'string' ? { message: options } : (options || {});
        window.dispatchEvent(new CustomEvent('open-confirm-dialog', {
            detail: {
                title: opts.title ?? {{ \Illuminate\Support\Js::from(__('Ungespeicherte Änderungen')) }},
                message: opts.message ?? '',
                confirmLabel: opts.confirmLabel ?? {{ \Illuminate\Support\Js::from(__('Änderungen verwerfen')) }},
                cancelLabel: opts.cancelLabel ?? {{ \Illuminate\Support\Js::from(__('Weiter bearbeiten')) }},
                alertOnly: false,
                signal: opts.signal ?? '',
                consequence: opts.consequence ?? '',
                resolve,
            },
        }));
    });

    window.notifyDialog = (message, title) => new Promise((resolve) => {
        window.dispatchEvent(new CustomEvent('open-confirm-dialog', {
            detail: {
                title: title ?? {{ \Illuminate\Support\Js::from(__('Hinweis')) }},
                message,
                confirmLabel: {{ \Illuminate\Support\Js::from(__('OK')) }},
                cancelLabel: '',
                alertOnly: true,
                resolve,
            },
        }));
    });
</script>
