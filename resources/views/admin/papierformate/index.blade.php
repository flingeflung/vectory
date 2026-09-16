<x-admin-layout>
    <script>
        window.__papierformateDirtyForms = new Set();

        // Maße + Fläche je Format für die Format-Kombinationen-Zeilen (wie
        // in Vietto S2) - einmal zentral hier statt pro Zeile im HTML
        // wiederholt, live nachschlagbar über die jeweils gewählte Format-ID.
        window.__papierformateFormatsById = @json($formats->mapWithKeys(fn ($f) => [$f->id => ['w' => $f->width_mm, 'h' => $f->height_mm]]));
        window.formatDimsLabel = function (id) {
            const f = window.__papierformateFormatsById[id];
            if (!f) {
                return '';
            }
            const flaeche = (f.w * f.h / 1000000).toFixed(3);
            return f.w + ' × ' + f.h + ' mm · ' + flaeche + ' m²';
        };
    </script>

    @if (session('status') === 'papierformate-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if (session('status') === 'papierformate-deleted')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gelöscht.') }}</x-flash-message>
    @endif
    @if (session('status') === 'papierformate-kombination-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if (session('status') === 'papierformate-kombination-deleted')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gelöscht.') }}</x-flash-message>
    @endif
    @if (session('status') === 'papierformate-import-done')
        @php($summary = session('import_summary'))
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">
            {{ __('Übernommen von :name: :formats Papierformate (:formatsSkipped bereits vorhanden), :combinations Format-Kombinationen (:combinationsSkipped bereits vorhanden).', [
                'name' => session('import_source_name'),
                'formats' => $summary['formats_copied'],
                'formatsSkipped' => $summary['formats_skipped'],
                'combinations' => $summary['combinations_copied'],
                'combinationsSkipped' => $summary['combinations_skipped'],
            ]) }}
        </x-flash-message>
    @endif

    <div x-data x-init="window.adminPageIsDirty = () => window.__papierformateDirtyForms.size > 0 || (window.papierformateCatalogIsDirty?.() ?? false)" class="flex flex-1 min-h-0 flex-col gap-3">
        {{-- Der Papierformate-Basiskatalog lebt jetzt in einem eigenen,
             großzügigeren Overlay statt in einem eigenen, immer sichtbaren
             Container - Ralf: "Das Feld für die Papierformate ist zu klein."
             Die Format-Kombinationen darunter gewinnen dadurch den Platz,
             den der Katalog-Container vorher belegt hat. --}}
        <div class="shrink-0 flex justify-end gap-2">
            @if ($otherTenants->isNotEmpty())
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'papierformate-uebernehmen' }))"
                    class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Von anderem Kunden übernehmen') }}
                </button>
            @endif
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'papierformate-katalog' }))"
                class="inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
            >
                {{ __('Papierformate verwalten') }}
            </button>
        </div>

        {{-- Format-Kombinationen: freigegebene Ausgangs-/Endformat-Paare,
             Vietto-Analyse "Print-Formate" Schritt 2 von 5. Ob A = E (Typ 1)
             oder A != E (Typ 2) ist rein informativ und wird live aus der
             Auswahl abgeleitet, nicht gespeichert - Schritt 4 nutzt das für
             den Heftungs-Filter. --}}
        <div class="flex flex-1 min-h-0 flex-col rounded-lg border border-gray-200 bg-white" x-data="{ creating: false }">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-100 p-3">
                <div>
                    <div class="text-sm font-semibold text-gray-700">{{ __('Format-Kombinationen') }}</div>
                    <p class="text-xs text-gray-400">{{ __('Freigegebene Paare aus Ausgangs- und Endformat. Nur freigegebene (aktive) Kombinationen stehen später bei der Projekt-Formatauswahl zur Verfügung.') }}</p>
                </div>
                @if ($formats->count() >= 1)
                    <button type="button" @click="creating = !creating" class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                        + {{ __('Neu') }}
                    </button>
                @endif
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-3 space-y-1.5">
                @if ($formats->isEmpty())
                    <p class="px-2 py-1 text-xs text-gray-400">{{ __('Erst Papierformate anlegen, bevor Kombinationen daraus gebildet werden können.') }}</p>
                @else
                    <form
                        x-show="creating"
                        x-cloak
                        method="POST"
                        action="{{ route('admin.papierformate.kombinationen.store') }}"
                        x-data="{ inputId: {{ $formats->first()->id }}, outputId: {{ $formats->first()->id }} }"
                        class="mb-1.5 flex flex-wrap items-end gap-2 rounded-md border border-gray-200 p-2"
                    >
                        @csrf
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Ausgangsformat') }}</label>
                            <select name="input_format_id" x-model.number="inputId" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
                                @foreach ($formats as $f)
                                    <option value="{{ $f->id }}" @class(['text-gray-400' => ! $f->active])>{{ $f->name }}{{ ! $f->active ? ' [i]' : '' }}</option>
                                @endforeach
                            </select>
                            <div class="mt-0.5 text-xs text-gray-400" x-text="window.formatDimsLabel(inputId)"></div>
                        </div>
                        <span class="pb-1.5 text-gray-400">&rarr;</span>
                        <div>
                            <label class="block text-xs text-gray-500">{{ __('Endformat') }}</label>
                            <select name="output_format_id" x-model.number="outputId" class="mt-0.5 rounded-md border-gray-300 py-1 text-sm">
                                @foreach ($formats as $f)
                                    <option value="{{ $f->id }}" @class(['text-gray-400' => ! $f->active])>{{ $f->name }}{{ ! $f->active ? ' [i]' : '' }}</option>
                                @endforeach
                            </select>
                            <div class="mt-0.5 text-xs text-gray-400" x-text="window.formatDimsLabel(outputId)"></div>
                        </div>
                        <span class="pb-1.5 text-xs font-medium" :class="inputId === outputId ? 'text-emerald-600' : 'text-indigo-600'" x-text="inputId === outputId ? {{ \Illuminate\Support\Js::from(__('A = E')) }} : {{ \Illuminate\Support\Js::from(__('A ≠ E')) }}"></span>
                        <div>
                            <label class="block text-xs text-gray-500" title="{{ __('Anzahl Falzungen (Brüche)') }}">{{ __('Falzungen') }}</label>
                            <input type="number" name="fold_count" min="0" step="1" class="mt-0.5 w-20 rounded-md border-gray-300 py-1 text-sm">
                        </div>
                        <label class="flex shrink-0 items-center gap-1 pb-2 text-xs text-gray-600">
                            <input type="checkbox" name="active" value="1" checked class="rounded border-gray-300">
                            {{ __('Freigegeben') }}
                        </label>
                        <button type="button" @click="creating = false" class="shrink-0 rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">
                            {{ __('Abbrechen') }}
                        </button>
                        <button type="submit" class="shrink-0 rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                            {{ __('Speichern') }}
                        </button>
                    </form>
                @endif

                @if ($combinations->isEmpty())
                    <p class="px-2 py-1 text-xs text-gray-400">{{ __('Noch keine Format-Kombinationen angelegt.') }}</p>
                @else
                    @foreach ($combinations as $combination)
                        <div x-data="{ dirty: false, inputId: {{ $combination->input_format_id }}, outputId: {{ $combination->output_format_id }} }" class="rounded-md border border-gray-200 px-2 py-1.5 {{ ! $combination->active ? 'bg-gray-50' : '' }}">
                            <form
                                method="POST"
                                action="{{ route('admin.papierformate.kombinationen.update', $combination) }}"
                                @input="dirty = window.formIsDirty($el, window.__papierformateDirtyForms)"
                                @submit="dirty = false; window.__papierformateDirtyForms.delete($el)"
                            >
                                @csrf
                                <div class="flex flex-wrap items-center gap-2">
                                    <div>
                                        <select name="input_format_id" x-model.number="inputId" @class(['rounded-md border-gray-300 py-1 text-sm', 'text-gray-400' => ! $combination->active])>
                                            @foreach ($formats as $f)
                                                <option value="{{ $f->id }}" @class(['text-gray-400' => ! $f->active])>{{ $f->name }}{{ ! $f->active ? ' [i]' : '' }}</option>
                                            @endforeach
                                        </select>
                                        <div class="text-xs text-gray-400" x-text="window.formatDimsLabel(inputId)"></div>
                                    </div>
                                    <span class="text-gray-400">&rarr;</span>
                                    <div>
                                        <select name="output_format_id" x-model.number="outputId" @class(['rounded-md border-gray-300 py-1 text-sm', 'text-gray-400' => ! $combination->active])>
                                            @foreach ($formats as $f)
                                                <option value="{{ $f->id }}" @class(['text-gray-400' => ! $f->active])>{{ $f->name }}{{ ! $f->active ? ' [i]' : '' }}</option>
                                            @endforeach
                                        </select>
                                        <div class="text-xs text-gray-400" x-text="window.formatDimsLabel(outputId)"></div>
                                    </div>
                                    <span class="text-xs font-medium" :class="inputId === outputId ? 'text-emerald-600' : 'text-indigo-600'" x-text="inputId === outputId ? {{ \Illuminate\Support\Js::from(__('A = E')) }} : {{ \Illuminate\Support\Js::from(__('A ≠ E')) }}"></span>
                                    <input type="number" name="fold_count" value="{{ $combination->fold_count }}" min="0" step="1" placeholder="–" class="w-16 rounded-md border-gray-300 py-1 text-xs" title="{{ __('Anzahl Falzungen (Brüche)') }}">
                                    <label class="flex shrink-0 items-center gap-1 text-xs text-gray-600">
                                        <input type="checkbox" name="active" value="1" @checked($combination->active) class="rounded border-gray-300">
                                        {{ __('Freigegeben') }}
                                    </label>
                                    <button type="submit" x-show="dirty" x-cloak class="shrink-0 rounded-md bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover">
                                        {{ __('Speichern') }}
                                    </button>
                                </div>
                                <div class="mt-1 pl-1">
                                    <label class="flex items-center gap-1 text-xs text-gray-500">
                                        {{ __('Bemerkung') }}
                                        <input type="text" name="remark" value="{{ $combination->remark }}" class="min-w-0 flex-1 rounded border-gray-300 py-0.5 text-xs">
                                    </label>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.papierformate.kombinationen.destroy', $combination) }}" x-ref="deleteForm{{ $combination->id }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <div class="mt-1 flex justify-end">
                                <button
                                    type="button"
                                    @click="window.deleteWithConfirm($refs['deleteForm{{ $combination->id }}'], { message: {{ \Illuminate\Support\Js::from(__('Diese Format-Kombination wirklich endgültig löschen?')) }} })"
                                    class="shrink-0 rounded-md border border-red-300 px-2 py-0.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                >
                                    {{ __('Löschen') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Katalog von einem anderen Kunden übernehmen (Schritt 5, pull-
         basiert): schlichter Formular-POST + Seiten-Reload, kein fetch
         nötig - reine, seltene Admin-Aktion, kein Konflikt mit
         ungespeicherten Zeilen-Eingaben auf dieser Seite (die Format-
         Kombinationen wurden gerade erst geladen). --}}
    @if ($otherTenants->isNotEmpty())
        <x-modal name="papierformate-uebernehmen" max-width="sm">
            <form method="POST" action="{{ route('admin.papierformate.uebernehmen') }}">
                @csrf
                <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Katalog übernehmen') }}</h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'papierformate-uebernehmen' }))"
                        class="text-gray-400 hover:text-gray-600"
                        aria-label="{{ __('Schließen') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <div class="p-4 text-sm">
                    <label class="block text-xs text-gray-500">{{ __('Kunde, von dem übernommen werden soll') }}</label>
                    <select name="source_tenant_id" required class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('– bitte wählen –') }}</option>
                        @foreach ($otherTenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-gray-400">{{ __('Übernimmt alle freigegebenen Papierformate und Format-Kombinationen des gewählten Kunden. Bereits vorhandene (gleicher Name bzw. gleiches Format-Paar) werden dabei übersprungen, nichts wird überschrieben.') }}</p>
                </div>
                <div class="shrink-0 border-t border-gray-100 p-3 flex justify-end gap-2">
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'papierformate-uebernehmen' }))"
                        class="rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >
                        {{ __('Abbrechen') }}
                    </button>
                    <button type="submit" class="rounded-md bg-btn-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-btn-primary-hover">
                        {{ __('Übernehmen') }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- Papierformate-Katalog-Overlay: Inhalt wird per fetch nachgeladen
         (gleiches Muster wie die Firma/Abteilung/Geschäftsbereich/Rolle-
         Verwalten-Overlays), damit der Katalog nicht doppelt gerendert
         werden muss (Basisseite + Overlay). --}}
    <x-modal name="papierformate-katalog" max-width="4xl" height="80vh" :dirty-check="'papierformateCatalogIsDirty'">
        <div class="flex h-full flex-col">
            <div class="shrink-0 flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Papierformate') }}</h3>
                    <p class="text-xs text-gray-400">{{ __('Einzelne Papiergrößen (z. B. "DIN A6 hoch"). Erlaubte Kombinationen aus zwei Formaten werden in der Übersicht dahinter festgelegt.') }}</p>
                </div>
                <button
                    type="button"
                    onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'papierformate-katalog' }))"
                    class="text-gray-400 hover:text-gray-600"
                    aria-label="{{ __('Schließen') }}"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="papierformate-katalog-toast" x-data="{ show: false }" x-show="show" x-cloak x-transition.opacity class="mx-4 mt-2 shrink-0 rounded bg-green-50 px-3 py-1.5 text-xs text-green-700">
                {{ __('Gespeichert.') }}
            </div>
            <div id="papierformate-katalog-body" class="min-h-0 flex-1 overflow-y-auto px-4 py-3 text-sm">
                {{ __('Lädt…') }}
            </div>
        </div>
    </x-modal>

    <script>
        (function () {
            const body = () => document.getElementById('papierformate-katalog-body');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            let wasOpened = false;
            let savedSnapshot = null;

            // Snapshot-Vergleich statt eines geteilten Dirty-Sets: der
            // Katalog wird hier per innerHTML-Tausch MEHRFACH nachgeladen
            // (nicht nur einmal per Seiten-Reload wie bei den
            // Format-Kombinationen) - ein Set voller Element-Referenzen
            // würde dabei verwaiste Einträge auf längst ersetzte, nicht
            // mehr im DOM hängende Knoten ansammeln. Gleiches Muster wie
            // company-manager/department-manager usw.
            const serializeAllForms = () => [...body().querySelectorAll('form')]
                .map((form) => new URLSearchParams(new FormData(form)).toString())
                .join('|');

            const snapshot = () => {
                savedSnapshot = serializeAllForms();
            };

            window.papierformateCatalogIsDirty = () => {
                return savedSnapshot !== null && serializeAllForms() !== savedSnapshot;
            };

            const load = async () => {
                await window.reloadManageListPreservingEdits(body(), {{ \Illuminate\Support\Js::from(route('admin.papierformate.katalog')) }});
                snapshot();
            };

            window.addEventListener('open-modal', (event) => {
                if (event.detail !== 'papierformate-katalog') {
                    return;
                }
                wasOpened = true;
                load();
            });

            window.addEventListener('close-modal', (event) => {
                if (event.detail !== 'papierformate-katalog' || !wasOpened) {
                    return;
                }
                wasOpened = false;
                // Formatliste könnte sich geändert haben (neu/umbenannt/
                // deaktiviert) - die Ausgangs-/Endformat-Pulldowns bei den
                // Format-Kombinationen dahinter sollen das zeigen. Volles
                // Neuladen ist hier unkritisch, außer es gibt dort noch
                // ungespeicherte Eingaben - dann lieber stehen lassen, als
                // sie wegzuwischen.
                if (window.__papierformateDirtyForms.size === 0) {
                    window.location.reload();
                }
            });

            document.addEventListener('submit', async (event) => {
                if (!body() || !body().contains(event.target)) {
                    return;
                }
                event.preventDefault();
                const isRowForm = event.target.hasAttribute('data-row-form');

                const formData = new FormData(event.target);
                await fetch(event.target.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });
                await load();
                if (isRowForm) {
                    window.showManageSavedToast('papierformate-katalog-toast');
                }
            });
        })();
    </script>
</x-admin-layout>
