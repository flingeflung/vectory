<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Produkte') }}
        </h2>
    </x-slot>

    <div class="h-full flex flex-col p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-7xl mx-auto flex flex-1 min-h-0 flex-col">
            <div
                class="mb-3 flex shrink-0 flex-wrap items-center gap-3 text-sm"
                x-data="{
                    term: {{ \Illuminate\Support\Js::from($search) }},
                    linked: {{ \Illuminate\Support\Js::from($linked ?? '') }},
                    searchTimer: null,
                    onInput() {
                        clearTimeout(this.searchTimer);
                        this.searchTimer = setTimeout(() => this.$dispatch('produkte-search', { term: this.term, linked: this.linked }), 400);
                    },
                    onLinkedChange() {
                        this.$dispatch('produkte-search', { term: this.term, linked: this.linked });
                    },
                }"
            >
                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('Suche') }}:</span>
                    <input
                        type="search"
                        x-model="term"
                        @input="onInput()"
                        placeholder="{{ __('Produktnr., -bezeichnung, Gruppe...') }}"
                        class="w-64 rounded-md border-gray-300 py-1 text-sm"
                    >
                </label>

                <label class="flex items-center gap-1.5">
                    <span class="text-gray-500">{{ __('Projektverknüpfung') }}:</span>
                    <select x-model="linked" @change="onLinkedChange()" class="rounded-md border-gray-300 py-1 text-sm">
                        <option value="">{{ __('– Alle –') }}</option>
                        <option value="yes">{{ __('mit') }}</option>
                        <option value="no">{{ __('ohne') }}</option>
                    </select>
                </label>
            </div>

            {{--
                Kein window.liveFilterSearch hier (anders als sonst üblich,
                siehe CLAUDE.md) - die generische Variante tauscht nur das
                HTML aus, kennt aber nicht den offset/hasMore-Zustand fürs
                Nachladen. Gleiches Problem/gleiche Lösung wie beim
                Projektverknüpfungen-Picker (connection-add-body.blade.php,
                runSearch()): eigener kleiner Fetch-Handler, der nach dem
                Tausch data-offset/data-has-more aus dem frischen HTML
                zurück in den Alpine-Zustand liest.
            --}}
            <div
                id="produkte-list"
                class="flex flex-1 min-h-0 flex-col"
                data-offset="{{ $products->count() }}"
                data-has-more="{{ $hasMore ? '1' : '0' }}"
                x-data="{
                    loading: false,
                    offset: {{ $products->count() }},
                    hasMore: {{ $hasMore ? 'true' : 'false' }},
                    loadingMore: false,
                    term: {{ \Illuminate\Support\Js::from($search) }},
                    linked: {{ \Illuminate\Support\Js::from($linked ?? '') }},
                    async runSearch({ term, linked }) {
                        this.term = term;
                        this.linked = linked;
                        this.loading = true;
                        try {
                            const url = {{ \Illuminate\Support\Js::from(route('produkte')) }} + '?q=' + encodeURIComponent(term) + '&linked=' + encodeURIComponent(linked) + '&sort={{ $sort }}&direction={{ $direction }}';
                            const html = await fetch(url).then((r) => r.text());
                            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('produkte-list');
                            const current = document.getElementById('produkte-list');
                            if (fresh && current) {
                                current.innerHTML = fresh.innerHTML;
                                this.offset = parseInt(fresh.dataset.offset || '0', 10);
                                this.hasMore = fresh.dataset.hasMore === '1';
                            }
                            history.replaceState(null, '', url);
                        } finally {
                            this.loading = false;
                        }
                    },
                    initScrollSentinel(el) {
                        new IntersectionObserver((entries) => {
                            if (entries[0].isIntersecting) { this.loadMore(); }
                        }, { root: el.closest('[data-scroll-root]'), rootMargin: '300px' }).observe(el);
                    },
                    async loadMore() {
                        if (this.loadingMore || ! this.hasMore) return;
                        this.loadingMore = true;
                        try {
                            const url = {{ \Illuminate\Support\Js::from(route('produkte.mehr')) }}
                                + '?offset=' + this.offset
                                + '&q=' + encodeURIComponent(this.term)
                                + '&linked=' + encodeURIComponent(this.linked)
                                + '&sort={{ $sort }}&direction={{ $direction }}';
                            const response = await fetch(url);
                            if (! response.ok) return;
                            const html = await response.text();
                            document.getElementById('produkte-rows').insertAdjacentHTML('beforeend', html);
                            this.hasMore = response.headers.get('X-Has-More') === '1';
                            this.offset += 500;
                        } finally {
                            this.loadingMore = false;
                        }
                    },
                }"
                @produkte-search.window="runSearch($event.detail)"
            >
                <div class="mb-3 shrink-0 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span>{{ __('Produkte gesamt') }}: <strong class="text-gray-800">{{ $totalCount }}</strong></span>
                        @if ($search !== '' || $linked !== null)
                            <span>{{ __('gefiltert') }}: <strong class="text-gray-800">{{ $total }}</strong></span>
                        @endif
                    </div>
                </div>

                <div data-scroll-root class="bg-white shadow-sm sm:rounded-lg flex flex-1 min-h-0 flex-col">
                    <div class="flex-1 min-h-0 overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <x-sortable-th field="product_number" :sort="$sort" :direction="$direction">{{ __('Produktnr.') }}</x-sortable-th>
                                    <x-sortable-th field="name" :sort="$sort" :direction="$direction">{{ __('Produktbezeichnung') }}</x-sortable-th>
                                    <x-sortable-th field="group_number" :sort="$sort" :direction="$direction">{{ __('Produktgruppennr.') }}</x-sortable-th>
                                    <x-sortable-th field="group_name" :sort="$sort" :direction="$direction">{{ __('Produktgruppenbezeichnung') }}</x-sortable-th>
                                    <x-sortable-th field="extra_text" :sort="$sort" :direction="$direction">{{ __('Produktzusatztext') }}</x-sortable-th>
                                    <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left font-medium text-gray-500 whitespace-nowrap">{{ __('Projektverknüpfung') }}</th>
                                </tr>
                            </thead>
                            <tbody id="produkte-rows" class="divide-y divide-gray-100 bg-white">
                                @include('produkte.partials.rows')
                            </tbody>
                        </table>

                        {{-- Sentinel MUSS innerhalb des scrollenden Bereichs stehen
                             (nicht als Geschwister-Element daneben) - sonst ist er
                             permanent sichtbar und der Observer feuert sofort statt
                             erst beim Herunterscrollen ans Listenende. --}}
                        <div x-show="hasMore" x-cloak x-init="initScrollSentinel($el)" class="py-2 text-center text-xs text-gray-400">
                            <span x-show="loadingMore" x-cloak>{{ __('Lädt…') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
