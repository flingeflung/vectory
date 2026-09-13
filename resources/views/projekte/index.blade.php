<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projekte') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div id="projekte-content" class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <div class="mb-3 flex shrink-0 items-center gap-2">
                @if (auth()->user()->can('project.create'))
                    <button
                        type="button"
                        onclick="window.openProjectCreate()"
                        class="inline-flex items-center rounded-md bg-btn-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-btn-primary-hover"
                    >
                        + {{ __('Neues Projekt') }}
                    </button>
                @elseif (auth()->user()->can('project.request'))
                    <button
                        type="button"
                        onclick="window.openProjectRequest()"
                        class="inline-flex items-center rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                    >
                        {{ __('Projekt anfragen') }}
                    </button>
                @endif

                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-modal', 'projektfilter')"
                    class="inline-flex items-center rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-btn-secondary-hover {{ ! empty($filters) ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-btn-secondary-border bg-btn-secondary text-gray-700' }}"
                >
                    {{ __('Projektfilter') }}
                    @if (! empty($filters))
                        <span class="ml-1.5 rounded-full bg-indigo-600 px-1.5 text-xs text-white">{{ count($filters) }}</span>
                    @endif
                </button>

                @if (! empty($filters))
                    <a href="{{ route('projekte', array_filter(['sort' => $sort, 'direction' => $direction, 'projektfilter_submitted' => 1])) }}" class="text-sm text-gray-500 hover:text-gray-700">
                        {{ __('Filter zurücksetzen') }}
                    </a>
                @endif

                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-modal', 'anzeigefilter')"
                    class="inline-flex items-center rounded-md border border-btn-secondary-border bg-btn-secondary px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-btn-secondary-hover"
                >
                    {{ __('Anzeigefilter') }}
                </button>

                {{-- "Meine Projektgruppen" (Ralf, 2026-09-13, analog Viettos
                     Gruppieren-Funktion) - Häkchen-Spalte in der Tabelle
                     ein-/ausblenden + Verwaltungs-Modal öffnen. --}}
                <button
                    type="button"
                    x-data
                    @click="$store.projectGrouping.toggleColumn(); $dispatch('open-modal', 'projektgruppen-panel-uebersicht')"
                    class="inline-flex items-center rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-btn-secondary-hover"
                    :class="$store.projectGrouping.active ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-btn-secondary-border bg-btn-secondary text-gray-700'"
                >
                    {{ __('Gruppieren') }}
                </button>
            </div>

            <div class="mb-3 shrink-0 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span>{{ __('Projekte gesamt') }}: <strong class="text-gray-800">{{ $totalCount }}</strong></span>
                    @if (! empty($filters))
                        <span>{{ __('gefiltert') }}: <strong class="text-gray-800">{{ $totalFiltered }}</strong></span>
                    @endif
                </div>

                @if (! empty($filters['schnellsuche'] ?? null))
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <span class="text-gray-500">{{ __('Schnellsuche') }}:</span>
                        <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">
                            "{{ $filters['schnellsuche'] }}"
                            <a href="{{ route('projekte') }}" class="text-indigo-400 hover:text-indigo-700" title="{{ __('Schnellsuche verlassen') }}">&times;</a>
                        </span>
                    </div>
                @elseif (! empty($filterChips))
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <span class="text-gray-500">{{ __('Filter') }}:</span>
                        @foreach ($filterChips as $chip)
                            <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">
                                {{ $chip['label'] }}: {{ $chip['value'] }}
                                <a
                                    href="{{ route('projekte', array_filter(['sort' => $sort, 'direction' => $direction, 'projektfilter_submitted' => 1, 'filter' => collect($filters)->except($chip['key'])->all()])) }}"
                                    class="text-indigo-400 hover:text-indigo-700"
                                    title="{{ __('Filter entfernen') }}"
                                >&times;</a>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div
                class="bg-white shadow-sm sm:rounded-lg flex flex-1 min-h-0 flex-col"
                x-data="{
                    offset: {{ $projects->count() }},
                    hasMore: {{ $hasMore ? 'true' : 'false' }},
                    loadingMore: false,
                    initScrollSentinel(el) {
                        new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting) { this.loadMore(); }
                        }, { root: el.closest('[data-scroll-root]'), rootMargin: '300px' }).observe(el);
                    },
                    async loadMore() {
                        if (this.loadingMore || ! this.hasMore) return;
                        this.loadingMore = true;
                        try {
                            const params = new URLSearchParams(window.location.search);
                            params.set('offset', this.offset);
                            const url = {{ \Illuminate\Support\Js::from(route('projekte.mehr')) }} + '?' + params.toString();
                            const response = await fetch(url);
                            if (! response.ok) return;
                            const html = await response.text();
                            const rowsEl = document.getElementById('projekte-rows');
                            const beforeCount = rowsEl.children.length;
                            rowsEl.insertAdjacentHTML('beforeend', html);
                            // Ralf-Bug-Report: Häkchen bei nachgeladenen Zeilen
                            // (Gruppieren-Spalte) reagierten nicht - insertAdjacentHTML
                            // fügt reines HTML ein, Alpine bindet :checked/@change/
                            // :disabled aber nur beim initialen Scan. Neu eingefügte
                            // Zeilen müssen explizit nachinitialisiert werden.
                            Array.from(rowsEl.children).slice(beforeCount).forEach((row) => Alpine.initTree(row));
                            this.hasMore = response.headers.get('X-Has-More') === '1';
                            this.offset += {{ $pageSize }};
                        } finally {
                            this.loadingMore = false;
                        }
                    },
                }"
            >
                <div data-scroll-root class="flex-1 min-h-0 overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                {{--
                                    Ralf-Bug-Report: bei reopen_group ist active von
                                    Anfang an true (server-geseedet), aber x-cloak
                                    versteckt die Spalte trotzdem kurz, bis Alpine
                                    beim Initialisieren hier ankommt - sichtbares
                                    Aus-/Wieder-Einblenden samt Sprung der Tabelle.
                                    x-cloak nur setzen, wenn wir NICHT schon wissen,
                                    dass die Spalte von Anfang an sichtbar sein soll.
                                --}}
                                <th
                                    x-show="$store.projectGrouping.active"
                                    @unless (request()->filled('reopen_group')) x-cloak @endunless
                                    class="sticky top-0 z-10 bg-gray-50 px-2 py-3"
                                ></th>
                                <x-sortable-th field="source_pn" :sort="$sort" :direction="$direction">{{ __('PN') }}</x-sortable-th>
                                @foreach ($columns as $column)
                                    @if (in_array($column['key'], ['title', 'version', 'status', 'workflow'], true))
                                        <x-sortable-th :field="$column['key']" :sort="$sort" :direction="$direction">{{ $column['label'] }}</x-sortable-th>
                                    @else
                                        <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ $column['label'] }}</th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="projekte-rows" class="divide-y divide-gray-100">
                            @include('projekte.partials.rows')
                        </tbody>
                    </table>

                    {{-- Sentinel MUSS innerhalb des scrollenden Bereichs stehen
                         (nicht als Geschwister daneben) - sonst ist er permanent
                         sichtbar und der Observer feuert sofort statt erst beim
                         Herunterscrollen ans Listenende (gleiche Lektion wie bei
                         Produkte/Verknüpfen-Modal). --}}
                    <div x-show="hasMore" x-cloak x-init="initScrollSentinel($el)" class="py-2 text-center text-xs text-gray-400">
                        <span x-show="loadingMore" x-cloak>{{ __('Lädt…') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('projekte.partials.project-group-modal', [
        'project' => null,
        'reopen' => request()->filled('reopen_group'),
        'reopenGroups' => $reopenGroups,
    ])

    @if (request()->filled('reopen_group'))
        {{--
            Ralf-Bug-Report (zwei Runden): "Projekte dieser Gruppe anzeigen"
            ist ein normaler Seitenaufruf (neuer gefilterter Filter) - das
            Panel ging dabei zu, und ein erster Fix-Versuch (Panel-Inhalt
            per Fetch NACH dem ersten Rendern nachladen) ließ ihn sichtbar
            "flushen und neu laden". Jetzt kommt der komplette Anfangs-
            zustand (Häkchen-Spalte, Panel-Box, Panel-INHALT inkl.
            Mitgliederliste) direkt mit diesem Seitenaufruf mit - kein
            zweiter Request mehr nötig für den ersten Anblick, dadurch kein
            Nachladen/Flackern mehr. Dieses Script läuft synchron beim
            Parsen, also VOR Alpine.start().
        --}}
        <script>
            window.__projectGroupingInitial = {
                active: true,
                groupId: '{{ (int) request()->query('reopen_group') }}',
                memberIds: @json($reopenMemberIds),
            };
        </script>
    @endif

    <x-modal name="anzeigefilter" max-width="xl" :show="$errors->any()" :dirty-check="'anzeigefilterIsDirty'">
        @include('projekte.partials.anzeigefilter-form')
    </x-modal>

    <x-modal name="projektfilter" max-width="xl" :show="request()->boolean('reopen_filter')" :dirty-check="'projektfilterIsDirty'">
        @include('projekte.partials.projektfilter-form')
    </x-modal>

    <script>
        (function () {
            const form = () => document.getElementById('anzeigefilter-form');
            let savedSnapshot = null;

            const serializeForm = (f) => f ? new URLSearchParams(new FormData(f)).toString() : null;
            const snapshot = () => { savedSnapshot = serializeForm(form()); };

            window.anzeigefilterIsDirty = () => {
                const current = serializeForm(form());
                return current !== null && current !== savedSnapshot;
            };

            // Deckt zwei Fälle ab: Button-Klick (Modal wird gerade geöffnet)
            // und Neuladen mit bereits offenem Modal (Validierungsfehler).
            // Zwei Microtask-Ticks, weil die Spaltenliste erst von Alpine
            // (x-for/x-if) ins DOM gerendert wird, nicht schon beim reinen
            // HTML-Parsing. requestAnimationFrame wäre hier riskant: Browser
            // setzen rAF in Hintergrund-Tabs aus, Microtasks nicht.
            const snapshotWhenSettled = () => queueMicrotask(() => queueMicrotask(snapshot));

            window.addEventListener('open-modal', (event) => {
                if (event.detail === 'anzeigefilter') {
                    snapshotWhenSettled();
                }
            });

            if (document.readyState === 'complete') {
                snapshotWhenSettled();
            } else {
                window.addEventListener('load', snapshotWhenSettled);
            }
        })();

        (function () {
            const form = () => document.getElementById('projektfilter-form');
            let savedSnapshot = null;

            const serializeForm = (f) => f ? new URLSearchParams(new FormData(f)).toString() : null;
            const snapshot = () => { savedSnapshot = serializeForm(form()); };

            window.projektfilterIsDirty = () => {
                const current = serializeForm(form());
                return current !== null && current !== savedSnapshot;
            };

            const snapshotWhenSettled = () => queueMicrotask(() => queueMicrotask(snapshot));

            window.addEventListener('open-modal', (event) => {
                if (event.detail === 'projektfilter') {
                    snapshotWhenSettled();
                }
            });

            if (document.readyState === 'complete') {
                snapshotWhenSettled();
            } else {
                window.addEventListener('load', snapshotWhenSettled);
            }
        })();
    </script>
</x-app-layout>
