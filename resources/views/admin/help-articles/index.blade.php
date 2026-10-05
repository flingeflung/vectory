<x-admin-layout>
    <script>
        window.__helpArticlesDirtyForms = new Set();
        // Zustand des Navigationsbaums (eingeklappte Äste, Kontextmenü zum Einfügen) - Ralf, 2026-10-05
        document.addEventListener('alpine:init', () => {
            Alpine.store('helpTree', {
                collapsed: {{ \Illuminate\Support\Js::from($collapsed) }},
                menu: { open: false, x: 0, y: 0, id: null, depth: 1 },
                maxDepth: {{ \App\Models\HelpArticle::MAX_DEPTH }},
                isCollapsed(id) { return this.collapsed.includes(id); },
                toggle(id) {
                    this.collapsed = this.isCollapsed(id) ? this.collapsed.filter((x) => x !== id) : [...this.collapsed, id];
                    fetch({{ \Illuminate\Support\Js::from(route('admin.hilfeseiten.baumzustand')) }}, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': {{ \Illuminate\Support\Js::from(csrf_token()) }} },
                        body: JSON.stringify({ collapsed: this.collapsed }),
                    });
                },
                openMenu(event, id, depth) {
                    this.menu = { open: true, x: Math.min(event.clientX, window.innerWidth - 260), y: Math.min(event.clientY, window.innerHeight - 110), id, depth };
                },
                openMenuAt(el, id, depth) {
                    const rect = el.getBoundingClientRect();
                    this.menu = { open: true, x: Math.min(rect.left, window.innerWidth - 260), y: Math.min(rect.bottom, window.innerHeight - 110), id, depth };
                },
                close() { this.menu.open = false; },
                async insert(mode) {
                    const id = this.menu.id;
                    this.close();
                    if (window.adminPageIsDirty && window.adminPageIsDirty() && ! (await window.confirmDialog({{ \Illuminate\Support\Js::from(__('Ungespeicherte Änderungen')) }}))) {
                        return;
                    }
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = {{ \Illuminate\Support\Js::from(route('admin.hilfeseiten.store')) }};
                    form.style.display = 'none';
                    Object.entries({ _token: {{ \Illuminate\Support\Js::from(csrf_token()) }}, title: {{ \Illuminate\Support\Js::from(__('Neue Seite')) }}, after: id, mode }).forEach(([name, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    });
                    document.body.appendChild(form);
                    window.__helpArticlesDirtyForms.clear();
                    form.submit();
                },
            });
        });
    </script>

    <div
        x-data
        x-show="$store.helpTree.menu.open"
        x-cloak
        @click.outside="$store.helpTree.close()"
        @keydown.escape.window="$store.helpTree.close()"
        @scroll.window="$store.helpTree.close()"
        :style="'left:' + $store.helpTree.menu.x + 'px; top:' + $store.helpTree.menu.y + 'px'"
        class="fixed z-50 w-60 rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg"
    >
        <button type="button" @click="$store.helpTree.insert('sibling')" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-100">{{ __('Darunter einfügen (gleiche Ebene)') }}</button>
        <button type="button" @click="$store.helpTree.insert('child')" :disabled="$store.helpTree.menu.depth >= $store.helpTree.maxDepth" :title="$store.helpTree.menu.depth >= $store.helpTree.maxDepth ? @js(__('Tiefer als vier Ebenen geht nicht.')) : ''" class="block w-full px-3 py-1.5 text-left text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-white">{{ __('Darunter einfügen (eingerückt)') }}</button>
    </div>

    @if (session('status') === 'help-article-updated')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gespeichert.') }}</x-flash-message>
    @endif
    @if (session('help_error'))
        <div class="mb-3 shrink-0 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ session('help_error') }}</div>
    @endif
    @if (session('status') === 'help-article-deleted')
        <x-flash-message class="mb-3 shrink-0 px-3 py-2 text-sm">{{ __('Gelöscht.') }}</x-flash-message>
    @endif

    <div x-data x-init="window.adminPageIsDirty = () => window.__helpArticlesDirtyForms.size > 0" class="flex flex-1 min-h-0 flex-col">
        @include('admin.help-articles.partials.content')
    </div>
</x-admin-layout>
