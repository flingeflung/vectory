{{--
    Modell/System (Ralf, 2026-09-13): verknüpft echte Produkte aus dem
    Mini-PIM statt Freitext - analog Viettos Modell-Zuordnung
    (ajax_getmodelle.php/ajax_modelsadd.php), aber ohne "Neues Modell
    anlegen", Modell/Artikel-Übersicht und PSP-Verknüpfung (bewusst nicht
    nachgebaut, Ralf). Sofort-Toggle wie im Vorbild - kein Speichern-Button.
    Caption bleibt pro Kunde änderbar (Attribute::LABEL_EDITABLE_SYSTEM_
    FIELDS), das ist $field->label hier.
--}}
<div id="project-products-{{ $project->id }}">
    <label class="block text-xs text-gray-500">{{ $field->label }}</label>
    <div class="mt-0.5 text-gray-700">
        @forelse ($project->products as $product)
            {{ $product->name }} ({{ $product->product_number }}){{ ! $loop->last ? ', ' : '' }}
        @empty
            <span class="text-gray-400">{{ __('– nicht zugewiesen –') }}</span>
        @endforelse
    </div>
    <button
        type="button"
        x-data
        @click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'produkte-verknuepfen-{{ $project->id }}' })); window.dispatchEvent(new CustomEvent('produkte-picker-open-{{ $project->id }}'))"
        class="mt-1 inline-flex items-center rounded-md border border-gray-300 bg-btn-secondary px-2 py-0.5 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover"
    >
        {{ __('Verknüpfte Produkte verwalten') }}
    </button>
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
        @produkte-picker-open-{{ $project->id }}.window="runSearch()"
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
            <input
                type="search"
                x-model="term"
                @input="onSearchInput()"
                :disabled="loading"
                placeholder="{{ __('Produktnr. oder -bezeichnung') }}"
                class="w-full rounded-md border-gray-300 text-sm disabled:bg-gray-50"
            >
        </div>

        <div data-scroll-root class="min-h-0 flex-1 overflow-y-auto p-4 text-sm">
            <div id="product-picker-body-{{ $project->id }}">{{ __('Lädt…') }}</div>
        </div>
    </div>
</x-modal>
