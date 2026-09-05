<aside
    x-show="sidebarOpen"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    class="w-64 h-full shrink-0 bg-sidebar border-r border-gray-200 flex flex-col overflow-hidden"
>
    <div class="h-16 flex items-center px-4 border-b border-gray-100 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <x-application-logo class="h-8 w-auto shrink-0 fill-current text-topbar" />
            <span class="font-semibold text-gray-800 truncate">{{ config('app.name') }}</span>
        </a>
    </div>

    <div
        class="relative px-4 py-3 shrink-0"
        x-data="{
            term: '',
            results: [],
            open: false,
            loading: false,
            selectedIndex: -1,
            debounceTimer: null,
            onInput() {
                this.selectedIndex = -1;
                clearTimeout(this.debounceTimer);
                const value = this.term.trim();
                if (value.length < 3) {
                    this.results = [];
                    this.open = false;
                    return;
                }
                this.open = true;
                this.debounceTimer = setTimeout(() => this.fetchResults(value), 300);
            },
            async fetchResults(value) {
                this.loading = true;
                try {
                    const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.schnellsuche')) }} + '?q=' + encodeURIComponent(value));
                    this.results = response.ok ? await response.json() : [];
                } finally {
                    this.loading = false;
                }
            },
            moveSelection(delta) {
                if (! this.results.length) return;
                this.selectedIndex = (this.selectedIndex + delta + this.results.length) % this.results.length;
            },
            openProject(item) {
                this.close();
                this.term = '';
                window.dispatchEvent(new CustomEvent('open-project', { detail: { id: item.id } }));
            },
            submit() {
                if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                    this.openProject(this.results[this.selectedIndex]);
                    return;
                }
                const value = this.term.trim();
                if (value === '') return;
                this.close();
                window.location.href = {{ \Illuminate\Support\Js::from(route('projekte')) }} + '?filter%5Bschnellsuche%5D=' + encodeURIComponent(value);
            },
            close() {
                this.open = false;
            },
            statusClass(status) {
                return { 0: 'bg-gray-300', 1: 'bg-blue-500', 2: 'bg-green-600', 3: 'bg-red-500' }[status] ?? 'bg-gray-300';
            },
            statusLabel(status) {
                return ({{ \Illuminate\Support\Js::from($statusOptions ?? [0 => __('Geplant'), 1 => __('In Bearbeitung'), 2 => __('Beendet'), 3 => __('Verworfen')]) }})[status] ?? '';
            },
        }"
        @click.outside="close()"
    >
        <label for="quicksearch" class="sr-only">{{ __('Schnellsuche') }}</label>
        <form @submit.prevent="submit()" class="relative">
            <input
                id="quicksearch"
                type="search"
                autocomplete="off"
                x-model="term"
                @input="onInput()"
                @focus="if (results.length) open = true"
                @keydown.escape="close()"
                @keydown.down.prevent="moveSelection(1)"
                @keydown.up.prevent="moveSelection(-1)"
                placeholder="{{ __('Schnellsuche') }}"
                class="w-full rounded-md border-gray-300 text-sm pr-8 focus:border-topbar focus:ring-topbar"
            />
            <button
                type="submit"
                class="absolute inset-y-0 right-0 flex items-center pr-2 text-gray-400 hover:text-gray-600"
                aria-label="{{ __('Suchen') }}"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                </svg>
            </button>
        </form>

        <div
            x-show="open && results.length > 0"
            x-cloak
            class="absolute left-4 right-4 z-20 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-200 bg-white text-xs shadow-lg"
        >
            <template x-for="(item, index) in results" :key="item.id">
                <button
                    type="button"
                    @click="openProject(item)"
                    class="flex w-full items-center gap-1 px-2 py-1 text-left hover:bg-gray-50"
                    :class="{ 'bg-gray-50': index === selectedIndex }"
                >
                    <span class="shrink-0 font-semibold tabular-nums text-gray-800" x-text="item.pn"></span>
                    <img
                        :src="item.type_symbol ? '{{ asset('images/project-type-icons') }}/' + item.type_symbol : ''"
                        :title="item.type_name"
                        class="h-3 w-3 shrink-0 object-contain"
                        :class="{ invisible: ! item.type_symbol }"
                    >
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="statusClass(item.status)" :title="statusLabel(item.status)"></span>
                    <span class="truncate text-gray-600" :title="item.title" x-text="item.title"></span>
                </button>
            </template>
        </div>

        <div
            x-show="open && loading"
            x-cloak
            class="absolute left-4 right-4 z-20 mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-400 shadow-lg"
        >
            {{ __('Suche läuft…') }}
        </div>

        <div
            x-show="open && !loading && term.trim().length >= 3 && results.length === 0"
            x-cloak
            class="absolute left-4 right-4 z-20 mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs text-gray-400 shadow-lg"
        >
            {{ __('Kein Projekt gefunden') }}
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-2 space-y-1">
        <a
            href="{{ route('dashboard') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Startseite') }}
        </a>
        <a
            href="{{ route('projekte') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('projekte') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Projekte') }}
        </a>
        <button
            type="button"
            x-data
            @click="$dispatch('open-modal', 'favorites')"
            class="flex w-full items-center px-3 py-2 rounded-md text-sm font-medium text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover"
        >
            {{ __('Favoriten') }}
        </button>
        <a
            href="{{ route('aufgaben') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('aufgaben') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Aufgaben') }}
        </a>
        <a
            href="{{ route('illustrationen') }}"
            class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('illustrationen') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
        >
            {{ __('Illustrationen') }}
        </a>

        @can('access-admin')
            <div class="my-2 border-t border-gray-100"></div>

            <a
                href="{{ route('admin') }}"
                class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('admin') ? 'bg-sidebar-active text-sidebar-active-content' : 'text-sidebar-content hover:bg-sidebar-hover hover:text-sidebar-content-hover' }}"
            >
                {{ __('Admin') }}
            </a>
        @endcan
    </nav>
</aside>
