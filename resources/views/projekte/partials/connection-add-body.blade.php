<div
    x-data="{
        term: {{ \Illuminate\Support\Js::from($search) }},
        searchTimer: null,
        addingId: null, addLabel: '', addLabelReverse: '',
        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.runSearch(), 400);
        },
        async runSearch() {
            const url = `/projekte/{{ $project->id }}/verknuepfungen/neu?q=` + encodeURIComponent(this.term);
            const html = await fetch(url).then((r) => r.text());
            const fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('connection-list');
            const current = document.getElementById('connection-list');
            if (fresh && current) { current.innerHTML = fresh.innerHTML; }
        },
        startAdd(id) { this.addingId = id; this.addLabel = ''; this.addLabelReverse = ''; },
        confirmAdd(id) {
            fetch({{ \Illuminate\Support\Js::from(route('projekte.verknuepfungen.store', $project)) }}, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ related_project_id: id, label: this.addLabel, label_reverse: this.addLabelReverse }),
            }).then((response) => {
                if (! response.ok) {
                    window.notifyDialog({{ \Illuminate\Support\Js::from(__('Verknüpfen fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.')) }});
                    return;
                }
                window.reloadProjectConnectionModal();
            });
        },
        removeConnection(connectionId) {
            window.confirmDialog({
                title: {{ \Illuminate\Support\Js::from(__('Verknüpfung entfernen?')) }},
                message: {{ \Illuminate\Support\Js::from(__('Diese Verknüpfung wirklich entfernen?')) }},
                confirmLabel: {{ \Illuminate\Support\Js::from(__('Entfernen')) }},
                cancelLabel: {{ \Illuminate\Support\Js::from(__('Abbrechen')) }},
            }).then((ok) => {
                if (! ok) return;
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
         (Live-Suche-Konvention, siehe CLAUDE.md). --}}
    <div>
        <label class="block text-xs text-gray-500">{{ __('Projekt suchen (PN oder Bezeichnung)') }}</label>
        <input type="text" x-model="term" @input="onSearchInput()" autocomplete="off" class="mt-0.5 w-full rounded-md border-gray-300 text-sm">
    </div>

    <div id="connection-list" class="mt-3">
        <div class="mb-1 text-[11px] font-medium text-gray-500">{{ __('Verknüpfte Projekte') }}</div>
        @forelse ($connectedProjects as $p)
            @php $entry = $connections->get($p->id); @endphp
            <div class="border-b border-gray-100 py-1 last:border-0">
                <label class="flex items-start gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" checked @click.prevent="removeConnection({{ $entry->connection->id }})" class="mt-0.5 shrink-0 rounded border-gray-300">
                    <span>{{ $p->source_pn }} &ndash; {{ $p->title }}</span>
                </label>
                <div class="ml-5 text-xs text-gray-400">{{ $entry->label }}</div>
            </div>
        @empty
            <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
        @endforelse

        <div class="mb-1 mt-3 text-[11px] font-medium text-gray-500">{{ __('Andere Projekte') }}</div>
        @forelse ($otherProjects as $p)
            <div class="border-b border-gray-100 py-1 last:border-0">
                <label class="flex items-start gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" @click.prevent="addingId === {{ $p->id }} ? (addingId = null) : startAdd({{ $p->id }})" class="mt-0.5 shrink-0 rounded border-gray-300">
                    <span>{{ $p->source_pn }} &ndash; {{ $p->title }}</span>
                </label>
                <div x-show="addingId === {{ $p->id }}" x-cloak class="ml-5 mt-1 space-y-1.5 rounded-md border border-gray-200 bg-gray-50 p-2">
                    <input type="text" x-model="addLabel" list="connection-label-suggestions" placeholder="{{ __('Bezeichnung dieser Richtung (von diesem Projekt aus), z. B. Vorlage für') }}" class="w-full rounded-md border-gray-300 text-xs">
                    <input type="text" x-model="addLabelReverse" list="connection-label-suggestions" placeholder="{{ __('Bezeichnung der Rückrichtung, z. B. Kopie von') }}" class="w-full rounded-md border-gray-300 text-xs">
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="addingId = null" class="rounded border border-btn-secondary-border bg-btn-secondary px-2 py-1 text-xs font-medium text-gray-700 hover:bg-btn-secondary-hover">{{ __('Abbrechen') }}</button>
                        <button type="button" :disabled="!addLabel.trim() || !addLabelReverse.trim()" @click="confirmAdd({{ $p->id }})" class="rounded bg-btn-primary px-2 py-1 text-xs font-medium text-white hover:bg-btn-primary-hover disabled:cursor-not-allowed disabled:opacity-50">{{ __('Verknüpfen') }}</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-xs text-gray-400">{{ __('Keine Treffer.') }}</div>
        @endforelse
    </div>

    <datalist id="connection-label-suggestions">
        @foreach ($labelSuggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
</div>
