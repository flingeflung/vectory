{{--
    Globales Lösch-Bestätigungs-Overlay - anders als confirm-dialog.blade.php
    (einfaches Ja/Nein) unterstützt dieses zusätzlich eine optionale
    Umhängen-Auswahl (z.B. "Projekte einer anderen Art zuweisen").

    Ralf: die bisherige Inline-Variante (Löschen-Button wird durch
    Abbrechen/Endgültig-löschen an GLEICHER Bildschirmposition ersetzt) ist
    ein Sicherheitsrisiko - ein hastiger Doppelklick trifft versehentlich
    den endgültigen Löschen-Button. Ein echtes, separates Overlay kann das
    nicht, weil es an einer anderen Stelle erscheint.

    Aufruf aus JS (siehe window.deleteWithConfirm unten):
      const result = await window.deleteWithConfirm(formEl, {
          message: 'Wird bereits in 12 Projekten verwendet.',
          reassignOptions: [{ value: '3', label: 'Kurzanleitung' }, ...],   // optional, auch gruppiert: [{ group: 'Technische Doku', options: [...] }]
          reassignName: 'reassign_to',          // Name des Felds im Formular, Default 'reassign_to'
          reassignPlaceholder: '– nicht zugewiesen –',
          reassignRequired: false,              // true = kein "nicht zugewiesen" erlaubt (z.B. Rechte-Sets - jede Person braucht ein Set), Bestätigen-Button bleibt gesperrt bis eine Auswahl getroffen ist
      });
      // sendet formEl automatisch ab, wenn bestätigt - kein eigenes Handling nötig.
--}}
<div
    x-data="{
        show: false, title: '', message: '', confirmLabel: '', cancelLabel: '',
        reassignOptions: [], reassignPlaceholder: '', reassignValue: '', reassignRequired: false,
        resolve: null,
        get flatReassignOptions() {
            return this.reassignOptions.flatMap(o => o.options ? o.options : [o]);
        },
    }"
    x-on:open-delete-confirm-dialog.window="
        title = $event.detail.title;
        message = $event.detail.message;
        confirmLabel = $event.detail.confirmLabel;
        cancelLabel = $event.detail.cancelLabel;
        reassignOptions = $event.detail.reassignOptions;
        reassignPlaceholder = $event.detail.reassignPlaceholder;
        reassignRequired = $event.detail.reassignRequired;
        reassignValue = reassignRequired ? (flatReassignOptions[0]?.value ?? '') : '';
        resolve = $event.detail.resolve;
        $nextTick(() => show = true)
    "
    x-show="show"
    x-cloak
    x-on:keydown.escape.window="if (show) { show = false; resolve(null); }"
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
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0Z" />
                    </svg>
                </div>
                <div class="pt-1">
                    <h3 class="text-sm font-semibold text-gray-900" x-text="title"></h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="message"></p>
                </div>
            </div>

            <div x-show="reassignOptions.length > 0" class="mt-3">
                <select x-model="reassignValue" class="w-full rounded-md border-gray-300 text-sm">
                    <template x-if="!reassignRequired">
                        <option value="" x-text="reassignPlaceholder"></option>
                    </template>
                    <template x-for="group in reassignOptions" :key="group.group ?? group.value">
                        <template x-if="group.options">
                            <optgroup :label="group.group">
                                <template x-for="opt in group.options" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label"></option>
                                </template>
                            </optgroup>
                        </template>
                    </template>
                    <template x-for="opt in reassignOptions.filter(o => !o.options)" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    @click="show = false; resolve(null)"
                    class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    x-text="cancelLabel"
                ></button>
                <button
                    type="button"
                    :disabled="reassignRequired && !reassignValue"
                    @click="show = false; resolve({ reassignTo: reassignValue })"
                    class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="confirmLabel"
                ></button>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * Zeigt das Overlay, sendet bei Bestätigung das übergebene Formular
     * automatisch ab (Umhängen-Auswahl wird vorher ins Feld reassignName
     * geschrieben) - Aufrufer muss sich um nichts weiter kümmern.
     * Gibt true zurück, wenn bestätigt+abgeschickt wurde, sonst false.
     */
    window.deleteWithConfirm = async function (formEl, options = {}) {
        const result = await new Promise((resolve) => {
            window.dispatchEvent(new CustomEvent('open-delete-confirm-dialog', {
                detail: {
                    title: options.title ?? {{ \Illuminate\Support\Js::from(__('Wirklich endgültig löschen?')) }},
                    message: options.message ?? '',
                    confirmLabel: options.confirmLabel ?? {{ \Illuminate\Support\Js::from(__('Endgültig löschen')) }},
                    cancelLabel: options.cancelLabel ?? {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
                    reassignOptions: options.reassignOptions ?? [],
                    reassignPlaceholder: options.reassignPlaceholder ?? {{ \Illuminate\Support\Js::from(__('– nicht zugewiesen –')) }},
                    reassignRequired: options.reassignRequired ?? false,
                    resolve,
                },
            }));
        });

        if (result === null) {
            return false;
        }

        if (result.reassignTo !== undefined) {
            const field = formEl.querySelector(`[name="${options.reassignName ?? 'reassign_to'}"]`);
            if (field) field.value = result.reassignTo;
        }

        formEl.submit();
        return true;
    };
</script>
