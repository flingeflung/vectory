<div
    x-data="{
        term: {{ \Illuminate\Support\Js::from($search) }},
        searchTimer: null,
        addingId: null, addLabel: '', addLabelReverse: '',
        loading: false,
        otherOffset: {{ $otherProjects->count() }},
        otherHasMore: {{ $otherHasMore ? 'true' : 'false' }},
        loadingMore: false,
        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.runSearch(), 400);
        },
        async runSearch() {
            this.loading = true;
            window.showProjectConnectionLoading();
            try {
                const url = `/projekte/{{ $project->id }}/verknuepfungen/neu?q=` + encodeURIComponent(this.term);
                const html = await fetch(url).then((r) => r.text());
                const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('connection-list');
                const current = document.getElementById('connection-list');
                if (fresh && current) {
                    current.innerHTML = fresh.innerHTML;
                    current.scrollTop = 0;
                    this.otherOffset = parseInt(fresh.dataset.otherOffset || '0', 10);
                    this.otherHasMore = fresh.dataset.otherHasMore === '1';
                }
            } finally {
                this.loading = false;
                window.hideProjectConnectionLoading();
            }
        },
        // Ralf, 2026-09-11: bei ~6700 Projekten (Sanitär) lädt die Liste
        // andere Projekte nur die ersten {{ $pageSize }}, weitere kommen
        // beim Erreichen des Listenendes nach (unendliches Scrollen) statt
        // alles auf einmal. Bewusst KEINE geraden Anführungszeichen in
        // diesem Kommentarblock - die würden das umschließende x-data-
        // Attribut vorzeitig beenden.
        onListScroll(el) {
            if (this.loadingMore || ! this.otherHasMore) return;
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 150) {
                this.loadMore();
            }
        },
        async loadMore() {
            this.loadingMore = true;
            try {
                const url = `/projekte/{{ $project->id }}/verknuepfungen/mehr?q=` + encodeURIComponent(this.term) + `&offset=` + this.otherOffset;
                const response = await fetch(url);
                const html = await response.text();
                document.getElementById('other-projects-rows').insertAdjacentHTML('beforeend', html);
                this.otherHasMore = response.headers.get('X-Has-More') === '1';
                this.otherOffset += {{ $pageSize }};
            } finally {
                this.loadingMore = false;
            }
        },
        startAdd(id) { this.addingId = id; this.addLabel = ''; this.addLabelReverse = ''; },
        async confirmAdd(id) {
            if (! this.addLabel.trim() || ! this.addLabelReverse.trim()) {
                window.notifyDialog({{ \Illuminate\Support\Js::from(__('Bitte beide Bezeichnungen eintragen.')) }});
                return;
            }
            this.loading = true;
            window.showProjectConnectionLoading();
            const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.verknuepfungen.store', $project)) }}, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ related_project_id: id, label: this.addLabel, label_reverse: this.addLabelReverse }),
            });
            if (! response.ok) {
                this.loading = false;
                window.hideProjectConnectionLoading();
                window.notifyDialog({{ \Illuminate\Support\Js::from(__('Verknüpfen fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.')) }});
                return;
            }
            // Sanduhr bleibt an, bis der komplette Neuladen-Zyklus fertig ist
            // (reloadProjectConnectionModal blendet sie selbst wieder aus) -
            // sonst kurzes Flackern zwischen den beiden Fetches.
            window.reloadProjectConnectionModal();
        },
        removeConnection(connectionId) {
            window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Verknüpfung entfernen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Diese Verknüpfung wirklich entfernen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Entfernen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            }).then((ok) => {
                if (! ok) return;
                this.loading = true;
                window.showProjectConnectionLoading();
                fetch(`/projekte/{{ $project->id }}/verknuepfungen/${connectionId}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                }).then(() => window.reloadProjectConnectionModal());
            });
        },
    }"
>
    {{-- Ralf, 2026-09-11 (nach Vietto-Quellcode ajax_getpnconnections.php):
         EINE Liste über alle Projekte, verknüpfte zuerst - das Suchfeld
         filtert nur nach, lädt keine separate Trefferliste. Suchfeld bleibt
         bewusst AUSSERHALB von #connection-list (nur dieser Bereich wird
         beim Tippen ausgetauscht), sonst verliert es beim Tippen den Fokus
         (Live-Suche-Konvention, siehe CLAUDE.md).

         Ralf: bei ~6700 Projekten (Sanitär) dauert Hinzufügen/Entfernen/
         Suchen spürbar - Sanduhr (window.show/hideProjectConnectionLoading,
         siehe layouts/app.blade.php) plus Klicksperre (:disabled="loading")
         auf allen Kästchen/Buttons, sonst "verleitet das zum wilden
         Rumklicken". --}}
    <div>
        <label class="block text-xs text-gray-500">{{ __('Projekt suchen (PN oder Bezeichnung)') }}</label>
        <input type="text" x-model="term" @input="onSearchInput()" :disabled="loading" autocomplete="off" class="mt-0.5 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-50">
    </div>

    <div
        id="connection-list"
        class="mt-3 max-h-[50vh] overflow-y-auto"
        @scroll="onListScroll($event.target)"
        data-other-offset="{{ $otherProjects->count() }}"
        data-other-has-more="{{ $otherHasMore ? '1' : '0' }}"
    >
        <div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Verknüpfte Projekte') }}</div>
        @forelse ($connectedProjects as $p)
            @php $entry = $connections->get($p->id); @endphp
            <div class="border-b border-gray-100 py-1 last:border-0">
                {{-- Bewusst KEIN <label> um Checkbox+Text: ein <label> leitet
                     jeden Klick auf den Text automatisch an die Checkbox
                     weiter (Browser-Standardverhalten) - ein Klick auf den
                     Projektnamen hätte sonst ungewollt die Verknüpfung
                     entfernt (Ralf-Bug-Report). Nur die Checkbox selbst
                     soll klickbar sein. --}}
                <div class="flex items-start gap-1.5 text-xs text-gray-700">
                    <input type="checkbox" checked :disabled="loading" @click.prevent="removeConnection({{ $entry->connection->id }})" class="mt-0.5 shrink-0 rounded border-gray-300">
                    <span>{{ $p->source_pn }} &ndash; {{ $p->title }}</span>
                </div>
                <div class="ml-5 text-[11px] text-gray-400">{{ $entry->label }}</div>
            </div>
        @empty
            <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
        @endforelse

        <div class="mb-1 mt-3 text-[11px] font-medium text-gray-500">{{ __('Andere Projekte') }}</div>
        <div id="other-projects-rows">
            @if ($otherProjects->isEmpty())
                <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
            @else
                @include('projekte.partials.connection-other-project-rows')
            @endif
        </div>
        <div x-show="loadingMore" x-cloak class="py-1.5 text-center text-[11px] text-gray-400">{{ __('Lädt weitere Projekte…') }}</div>
    </div>

    <datalist id="connection-label-suggestions">
        @foreach ($labelSuggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
</div>
