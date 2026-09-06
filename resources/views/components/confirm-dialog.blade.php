{{--
    Globaler, gestylter Bestätigungs-/Hinweis-Dialog als Ersatz für die
    nativen confirm()/alert() Browser-Dialoge - die will Ralf in Vectory
    grundsätzlich nicht mehr sehen (siehe CLAUDE.md UI-Konventionen).
    Aufruf aus JS:
      const ok = await window.confirmDialog('Text …');
      const ok = await window.confirmDialog({ title, message, confirmLabel, cancelLabel });
      await window.notifyDialog('Text …'); // reiner Hinweis, nur ein OK-Button
--}}
<div
    x-data="{ show: false, title: '', message: '', confirmLabel: '', cancelLabel: '', alertOnly: false, resolve: null }"
    x-on:open-confirm-dialog.window="
        title = $event.detail.title;
        message = $event.detail.message;
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
    class="fixed inset-0 z-[60] overflow-y-auto"
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
            class="relative w-full max-w-sm rounded-lg bg-white p-5 shadow-xl"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        >
            <div class="flex items-start gap-3">
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
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    x-text="cancelLabel"
                ></button>
                <button
                    type="button"
                    @click="show = false; resolve(true)"
                    class="rounded-md px-3 py-1.5 text-sm font-medium text-white"
                    :class="alertOnly ? 'bg-gray-800 hover:bg-gray-700' : 'bg-red-600 hover:bg-red-700'"
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
