{{--
    Modell/System (Ralf, 2026-09-13): verknüpft echte Produkte aus dem
    Mini-PIM statt Freitext - analog Viettos Modell-Zuordnung
    (ajax_getmodelle.php/ajax_modelsadd.php), aber ohne "Neues Modell
    anlegen", Modell/Artikel-Übersicht und PSP-Verknüpfung (bewusst nicht
    nachgebaut, Ralf). Sofort-Toggle wie im Vorbild - kein Speichern-Button.
    Caption bleibt pro Kunde änderbar (Attribute::LABEL_EDITABLE_SYSTEM_
    FIELDS), das ist $field->label hier.

    Ralf, 2026-09-13 (Nachtrag): Textbutton durch das kleine wiederverwend-
    bare Stift-Symbol ersetzt (x-edit-icon-button, "nicht so aufdringlich
    wie Textbuttons"), plus Ketten-Symbol pro verknüpftem Produkt - zeigt
    per Klick, mit welchen ANDEREN Projekten dasselbe Produkt noch
    verknüpft ist (analog Viettos pruefe_conn() in ajax_getmodelle.php,
    dort nur im Picker sichtbar - hier direkt an der Feldzeile).
--}}
<div id="project-products-{{ $project->id }}">
    <div class="flex items-center gap-1.5">
        <label class="text-xs text-gray-500">{{ $field->label }}</label>
        <x-edit-icon-button modal="produkte-verknuepfen-{{ $project->id }}" :title="__('Verknüpfte Produkte verwalten')" />
    </div>
    <div class="mt-0.5 flex flex-wrap items-center gap-x-1 gap-y-0.5 text-gray-700">
        @forelse ($project->products as $product)
            @php($otherProjects = $product->projects->where('id', '!=', $project->id)->values())
            <span class="inline-flex items-center gap-0.5">
                {{ $product->name }} ({{ $product->product_number }})
                @if ($otherProjects->isNotEmpty())
                    <span x-data="{ open: false }" class="relative inline-block">
                        <button
                            type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            title="{{ trans_choice('Auch verknüpft mit :count weiterem Projekt|Auch verknüpft mit :count weiteren Projekten', $otherProjects->count(), ['count' => $otherProjects->count()]) }}"
                            class="text-gray-400 hover:text-gray-600"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute left-0 top-full z-10 mt-1 w-max max-w-xs rounded-md border border-gray-200 bg-white p-2 text-xs shadow-lg">
                            <div class="mb-1 font-medium text-gray-500">{{ __('Auch verknüpft mit:') }}</div>
                            @foreach ($otherProjects as $otherProject)
                                <div>
                                    <a
                                        href="#"
                                        onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('open-project', { detail: { id: {{ $otherProject->id }} } }))"
                                        class="text-indigo-600 hover:underline"
                                    >{{ $otherProject->source_pn }}</a>
                                    <span class="text-gray-400">{{ $otherProject->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    </span>
                @endif
                {{ ! $loop->last ? ',' : '' }}
            </span>
        @empty
            <span class="text-gray-400">{{ __('– nicht zugewiesen –') }}</span>
        @endforelse
    </div>
</div>

<x-modal name="produkte-verknuepfen-{{ $project->id }}" max-width="md">
    <div
        class="flex max-h-[70vh] flex-col"
        x-data="{
            term: '',
            searchTimer: null,
            loading: false,
            otherOffset: 0,
            otherHasMore: false,
            loadingMore: false,
            async runSearch() {
                this.loading = true;
                try {
                    const url = {{ \Illuminate\Support\Js::from(route('projekte.produkte.picker', $project)) }} + '?q=' + encodeURIComponent(this.term);
                    const html = await fetch(url).then((r) => r.text());
                    document.getElementById('product-picker-body-{{ $project->id }}').innerHTML = html;
                    const sentinel = document.querySelector('#product-picker-body-{{ $project->id }} [data-has-more]');
                    this.otherHasMore = sentinel?.dataset.hasMore === '1';
                    this.otherOffset = document.querySelectorAll('#product-picker-other-rows [data-product-row]').length;
                } finally {
                    this.loading = false;
                }
            },
            onSearchInput() {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.runSearch(), 400);
            },
            initScrollSentinel(el) {
                new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) { this.loadMore(); }
                }, { root: el.closest('[data-scroll-root]'), rootMargin: '300px' }).observe(el);
            },
            async loadMore() {
                if (this.loadingMore || ! this.otherHasMore) return;
                this.loadingMore = true;
                try {
                    const url = {{ \Illuminate\Support\Js::from(route('projekte.produkte.mehr', $project)) }}
                        + '?offset=' + this.otherOffset + '&q=' + encodeURIComponent(this.term);
                    const response = await fetch(url);
                    if (! response.ok) return;
                    const html = await response.text();
                    document.getElementById('product-picker-other-rows').insertAdjacentHTML('beforeend', html);
                    this.otherHasMore = response.headers.get('X-Has-More') === '1';
                    this.otherOffset += 500;
                } finally {
                    this.loadingMore = false;
                }
            },
            async toggle(productId) {
                const url = {{ \Illuminate\Support\Js::from(route('projekte.produkte.toggle', [$project, '__PRODUCT__'])) }}.replace('__PRODUCT__', productId);
                const html = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                }).then((r) => r.text());
                const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('project-products-{{ $project->id }}');
                const current = document.getElementById('project-products-{{ $project->id }}');
                if (fresh && current) { current.replaceWith(fresh); }
            },
        }"
        @open-modal.window="$event.detail === 'produkte-verknuepfen-{{ $project->id }}' && runSearch()"
    >
        <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ __('Verknüpfte Produkte') }}</h3>
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'produkte-verknuepfen-{{ $project->id }}' }))"
                class="text-gray-400 hover:text-gray-600"
                aria-label="{{ __('Schließen') }}"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="shrink-0 border-b border-gray-100 px-4 py-2">
            {{-- KEIN :disabled="loading" hier (Ralf-Bug-Report, zweites
                 Auftreten, siehe connection-add-body.blade.php): ein
                 fokussiertes Input verliert in jedem Browser sofort den
                 Fokus, sobald es disabled wird. --}}
            <input
                type="search"
                x-model="term"
                @input="onSearchInput()"
                placeholder="{{ __('Produktnr. oder -bezeichnung') }}"
                class="w-full rounded-md border-gray-300 text-sm"
            >
        </div>

        <div data-scroll-root class="min-h-0 flex-1 overflow-y-auto p-4 text-sm">
            <div id="product-picker-body-{{ $project->id }}">{{ __('Lädt…') }}</div>
        </div>
    </div>
</x-modal>
