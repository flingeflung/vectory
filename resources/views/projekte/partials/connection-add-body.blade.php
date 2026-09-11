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
        async loadMore() {
            if (this.loadingMore || ! this.otherHasMore) return;
            this.loadingMore = true;
            try {
                const url = `/projekte/{{ $project->id }}/verknuepfungen/mehr?q=` + encodeURIComponent(this.term) + `&offset=` + this.otherOffset;
                const response = await fetch(url);
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Nachladen fehlgeschlagen. Bitte erneut versuchen (z. B. kurz hoch- und wieder runterscrollen).')) }});
                    return;
                }
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
            try {
                const response = await fetch({{ \Illuminate\Support\Js::from(route('projekte.verknuepfungen.store', $project)) }}, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ related_project_id: id, label: this.addLabel, label_reverse: this.addLabelReverse }),
                });
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Verknüpfen fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.')) }});
                    return;
                }
                const rowHtml = await response.text();
                document.getElementById('connected-projects-rows').insertAdjacentHTML('beforeend', rowHtml);
                document.getElementById(`other-row-${id}`)?.remove();
                this.addingId = null;
            } finally {
                this.loading = false;
                window.hideProjectConnectionLoading();
            }
        },
        removeConnection(connectionId, otherProjectId) {
            window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Verknüpfung entfernen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Diese Verknüpfung wirklich entfernen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Entfernen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            }).then(async (ok) => {
                if (! ok) return;
                this.loading = true;
                window.showProjectConnectionLoading();
                try {
                    const response = await fetch(`/projekte/{{ $project->id }}/verknuepfungen/${connectionId}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    });
                    if (response.ok) {
                        document.getElementById(`connected-row-${otherProjectId}`)?.remove();
                    }
                } finally {
                    this.loading = false;
                    window.hideProjectConnectionLoading();
                }
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

         Ralf: bei ~6700 Projekten (Sanitär) war das komplette Neuladen der
         Liste nach jedem Hinzufügen/Entfernen spürbar langsam ("das geht
         alles von meiner Nutzungszeit ab"). Hinzufügen/Entfernen laden
         deshalb NICHT mehr die ganze Liste neu (window.reloadProjectConnectionModal
         wird dafür nicht mehr benutzt) - store()/destroy() geben nur noch
         die eine betroffene Zeile bzw. 204 zurück, der Client fügt sie
         direkt ein bzw. entfernt sie direkt (confirmAdd/removeConnection).
         Sanduhr (window.show/hideProjectConnectionLoading, siehe
         layouts/app.blade.php) plus Klicksperre (:disabled="loading") auf
         allen Kästchen/Buttons bleiben trotzdem, weil diese Fetches auf
         Sanitärs Datenmenge nicht beliebig schnell sind.

         Aus demselben Performance-Grund lädt "Andere Projekte" nur die
         ersten {{ $pageSize }} (loadMore()) statt alles auf einmal -
         weitere kommen über den Button "Weitere laden" am Listenende.
         Ursprünglich automatisch beim Scrollen (erst manuelle Scroll-
         Positions-Berechnung, dann ein IntersectionObserver) - bei Ralfs
         echten ~6700 Projekten blieb beides irgendwann stehen, obwohl der
         Server jeden einzelnen Abschnitt nachweislich fehlerfrei und
         schnell ausliefert (durchgetestet bis zum letzten Datensatz). Die
         Fehlerursache ließ sich clientseitig nicht zuverlässig
         reproduzieren - ein expliziter Button ist weniger elegant, aber
         ohne Scroll-Erkennungs-Unsicherheiten. loadMore() prüft
         response.ok: ein Serverfehler hätte sonst denselben Effekt wie
         "keine weiteren Projekte mehr" (X-Has-More-Header fehlt dann) und
         der Button wäre stillschweigend verschwunden.

         WICHTIG für Änderungen an diesem x-data-Block: KEINE geraden
         Anführungszeichen in JS-Kommentaren dort verwenden - die beenden
         das umschließende x-data="..."-Attribut vorzeitig, der Rest der
         Seite landet dann als Rohtext im Browser (ist hier schon zweimal
         passiert). Erklärungen gehören hierher in diesen Blade-Kommentar,
         nicht in JS-Kommentare innerhalb von x-data. --}}
    <div>
        <label class="block text-xs text-gray-500">{{ __('Projekt suchen (PN oder Bezeichnung)') }}</label>
        <input type="text" x-model="term" @input="onSearchInput()" :disabled="loading" autocomplete="off" class="mt-0.5 w-full rounded-md border-gray-300 text-sm disabled:bg-gray-50">
    </div>

    <div
        id="connection-list"
        class="mt-3 max-h-[50vh] overflow-y-auto"
        data-other-offset="{{ $otherProjects->count() }}"
        data-other-has-more="{{ $otherHasMore ? '1' : '0' }}"
    >
        <div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Verknüpfte Projekte') }}</div>
        <div id="connected-projects-rows">
            @if ($connectedProjects->isEmpty())
                <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
            @else
                @include('projekte.partials.connection-connected-project-rows')
            @endif
        </div>

        <div class="mb-1 mt-3 text-[11px] font-medium text-gray-500">{{ __('Andere Projekte') }}</div>
        <div id="other-projects-rows">
            @if ($otherProjects->isEmpty())
                <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
            @else
                @include('projekte.partials.connection-other-project-rows')
            @endif
        </div>
        <div x-show="otherHasMore" x-cloak class="py-1.5 text-center">
            <button
                type="button"
                @click="loadMore()"
                :disabled="loadingMore"
                class="rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-btn-secondary-hover disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span x-show="! loadingMore">{{ __('Weitere laden') }}</span>
                <span x-show="loadingMore" x-cloak>{{ __('Lädt…') }}</span>
            </button>
        </div>
    </div>

    <datalist id="connection-label-suggestions">
        @foreach ($labelSuggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
</div>
